<?php

namespace App\Modules\ImportExport\Services;

use App\Models\User;
use App\Modules\ImportExport\Models\ImportJob;
use App\Modules\ImportExport\Models\ImportStagedRow;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductCategory;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;

class ImportPreviewService
{
    private const PREVIEW_ROW_LIMIT = 25;

    public const TARGET_FIELDS = [
        'products' => ['sku', 'barcode', 'name', 'generic_name', 'composition', 'formulation', 'mrp', 'purchase_price', 'selling_price', 'reorder_level'],
        'suppliers' => ['name', 'contact_person', 'phone', 'email', 'pan_number', 'address', 'opening_balance'],
        'customers' => ['name', 'contact_person', 'phone', 'email', 'pan_number', 'address', 'credit_limit', 'opening_balance'],
        'units' => ['name', 'code', 'type', 'factor'],
        'companies' => ['name', 'legal_name', 'pan_number', 'phone', 'email', 'address', 'default_cc_rate'],
        'opening_stock' => ['sku', 'barcode', 'batch_no', 'quantity_available', 'purchase_price', 'mrp', 'expires_at'],
        'batches' => ['sku', 'barcode', 'batch_no', 'quantity_received', 'quantity_available', 'purchase_price', 'mrp', 'expires_at'],
    ];

    public function preview(string $target, UploadedFile $file, ?int $userId = null): ImportJob
    {
        $rows = $this->readRows($file->getRealPath(), $file->getClientOriginalExtension());
        $path = $file->store('imports/staged', 'local');
        $headers = array_values(array_unique($rows->flatMap(fn (array $row) => array_keys($row))->filter()->all()));

        return DB::transaction(function () use ($target, $file, $path, $rows, $headers, $userId) {
            $job = ImportJob::query()->create([
                'target' => $target,
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $path,
                'detected_columns' => $headers,
                'total_rows' => $rows->count(),
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'status' => 'previewed',
                'created_by' => $userId,
            ]);

            $this->persistPreviewRows($job, $rows);

            return $job->fresh('rows');
        });
    }

    public function confirm(int $jobId, array $mapping, ?User $user = null): ImportJob
    {
        return DB::transaction(function () use ($jobId, $mapping, $user) {
            $job = ImportJob::query()->lockForUpdate()->findOrFail($jobId);
            $job->rows()->delete();

            $required = $this->requiredFields($job->target);
            $mappedSystemFields = array_values(array_filter($mapping));
            $missing = array_values(array_diff($required, $mappedSystemFields));

            if (! empty($missing)) {
                $job->update([
                    'mapping' => $mapping,
                    'valid_rows' => 0,
                    'invalid_rows' => (int) $job->total_rows,
                    'status' => 'needs_mapping',
                ]);

                ImportStagedRow::query()->create([
                    'import_job_id' => $job->id,
                    'row_number' => 0,
                    'raw_data' => [],
                    'errors' => ['Missing required mapping: '.implode(', ', $missing)],
                    'status' => 'invalid',
                ]);

                return $job->fresh('rows');
            }

            $valid = 0;
            $invalid = 0;
            $rows = $this->readRows(
                Storage::disk('local')->path($job->stored_path),
                pathinfo($job->original_filename, PATHINFO_EXTENSION),
            );

            foreach ($rows as $index => $rawRow) {
                $mapped = $this->mapRow($rawRow, $mapping);

                try {
                    $this->insertMappedRow($job->target, $mapped, $user);
                    $valid++;

                    if ($index < self::PREVIEW_ROW_LIMIT) {
                        ImportStagedRow::query()->create([
                            'import_job_id' => $job->id,
                            'row_number' => $index + 1,
                            'raw_data' => $rawRow,
                            'mapped_data' => $mapped,
                            'status' => 'imported',
                        ]);
                    }
                } catch (\Throwable $throwable) {
                    $invalid++;

                    ImportStagedRow::query()->create([
                        'import_job_id' => $job->id,
                        'row_number' => $index + 1,
                        'raw_data' => $rawRow,
                        'mapped_data' => $mapped,
                        'errors' => [$throwable->getMessage()],
                        'status' => 'invalid',
                    ]);
                }
            }

            $job->update([
                'mapping' => $mapping,
                'valid_rows' => $valid,
                'invalid_rows' => $invalid,
                'status' => $invalid > 0 ? 'completed_with_errors' : 'completed',
            ]);

            return $job->fresh('rows');
        });
    }

    public function targetFields(): array
    {
        return self::TARGET_FIELDS;
    }

    public function requiredFields(string $target): array
    {
        return match ($target) {
            'products' => ['name'],
            'suppliers', 'customers', 'units', 'companies' => ['name'],
            'opening_stock', 'batches' => ['sku', 'batch_no', 'quantity_available'],
            default => [],
        };
    }

    public function sampleCsv(string $target): string
    {
        $fields = $this->targetFields()[$target] ?? [];
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $fields);
        fputcsv($handle, array_map(fn ($field) => 'sample_'.$field, $fields));
        rewind($handle);
        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $contents;
    }

    public function rejectedCsv(ImportJob $job): string
    {
        $rows = $job->rows()->where('status', 'invalid')->orderBy('row_number')->get();
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['row_number', 'errors', 'raw_data']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row->row_number,
                implode('; ', $row->errors ?? []),
                json_encode($row->raw_data),
            ]);
        }

        rewind($handle);
        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $contents;
    }

    private function mapRow(array $raw, array $mapping): array
    {
        $mapped = [];

        foreach ($mapping as $uploadedColumn => $systemField) {
            if ($systemField) {
                $mapped[$systemField] = $raw[$uploadedColumn] ?? null;
            }
        }

        return $mapped;
    }

    private function insertMappedRow(string $target, array $data, ?User $user): void
    {
        match ($target) {
            'companies' => Company::query()->firstOrCreate(
                ['name' => $this->requiredValue($data, 'name')],
                [
                    'tenant_id' => $user?->tenant_id,
                    'legal_name' => $data['legal_name'] ?? null,
                    'pan_number' => $data['pan_number'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'address' => $data['address'] ?? null,
                    'default_cc_rate' => (float) ($data['default_cc_rate'] ?? 0),
                    'created_by' => $user?->id,
                    'updated_by' => $user?->id,
                ],
            ),
            'units' => Unit::query()->firstOrCreate(
                ['company_id' => $user?->company_id, 'name' => $this->requiredValue($data, 'name')],
                [
                    'tenant_id' => $user?->tenant_id,
                    'code' => $data['code'] ?? null,
                    'type' => $data['type'] ?? 'both',
                    'factor' => (float) ($data['factor'] ?? 1),
                    'created_by' => $user?->id,
                    'updated_by' => $user?->id,
                ],
            ),
            'suppliers' => Supplier::query()->firstOrCreate(
                ['company_id' => $user?->company_id, 'name' => $this->requiredValue($data, 'name')],
                [
                    'tenant_id' => $user?->tenant_id,
                    'contact_person' => $data['contact_person'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'pan_number' => $data['pan_number'] ?? null,
                    'address' => $data['address'] ?? null,
                    'opening_balance' => (float) ($data['opening_balance'] ?? 0),
                    'current_balance' => (float) ($data['opening_balance'] ?? 0),
                    'created_by' => $user?->id,
                    'updated_by' => $user?->id,
                ],
            ),
            'customers' => Customer::query()->firstOrCreate(
                ['company_id' => $user?->company_id, 'name' => $this->requiredValue($data, 'name')],
                [
                    'tenant_id' => $user?->tenant_id,
                    'contact_person' => $data['contact_person'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'pan_number' => $data['pan_number'] ?? null,
                    'address' => $data['address'] ?? null,
                    'credit_limit' => (float) ($data['credit_limit'] ?? 0),
                    'opening_balance' => (float) ($data['opening_balance'] ?? 0),
                    'current_balance' => (float) ($data['opening_balance'] ?? 0),
                    'created_by' => $user?->id,
                    'updated_by' => $user?->id,
                ],
            ),
            'products' => $this->insertProduct($data, $user),
            'opening_stock', 'batches' => $this->insertBatch($data, $user),
            default => throw new \RuntimeException('Unsupported import target.'),
        };
    }

    private function insertProduct(array $data, ?User $user): void
    {
        $unitId = Unit::query()->where('company_id', $user?->company_id)->value('id');
        $categoryId = ProductCategory::query()->where('company_id', $user?->company_id)->value('id');

        Product::query()->firstOrCreate(
            ['company_id' => $user?->company_id, 'sku' => $data['sku'] ?? null, 'name' => $this->requiredValue($data, 'name')],
            [
                'tenant_id' => $user?->tenant_id,
                'store_id' => $user?->store_id,
                'barcode' => $data['barcode'] ?? null,
                'unit_id' => $unitId,
                'category_id' => $categoryId,
                'generic_name' => $data['generic_name'] ?? null,
                'composition' => $data['composition'] ?? null,
                'formulation' => $data['formulation'] ?? 'Other',
                'mrp' => (float) ($data['mrp'] ?? 0),
                'purchase_price' => (float) ($data['purchase_price'] ?? 0),
                'selling_price' => (float) ($data['selling_price'] ?? $data['mrp'] ?? 0),
                'reorder_level' => (int) ($data['reorder_level'] ?? 10),
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ],
        );
    }

    private function insertBatch(array $data, ?User $user): void
    {
        $product = Product::query()
            ->where('company_id', $user?->company_id)
            ->where(function ($query) use ($data) {
                $query->when($data['sku'] ?? null, fn ($builder, $sku) => $builder->orWhere('sku', $sku))
                    ->when($data['barcode'] ?? null, fn ($builder, $barcode) => $builder->orWhere('barcode', $barcode));
            })
            ->first();

        if (! $product) {
            throw new \RuntimeException('Product not found for stock row.');
        }

        $quantity = (float) ($data['quantity_available'] ?? $data['quantity_received'] ?? 0);

        if ($quantity <= 0) {
            throw new \RuntimeException('Batch quantity must be greater than zero.');
        }

        $batch = Batch::query()->firstOrCreate(
            ['company_id' => $user?->company_id, 'product_id' => $product->id, 'batch_no' => $this->requiredValue($data, 'batch_no')],
            [
                'tenant_id' => $user?->tenant_id,
                'store_id' => $user?->store_id,
                'barcode' => $data['barcode'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'quantity_received' => 0,
                'quantity_available' => 0,
                'purchase_price' => (float) ($data['purchase_price'] ?? 0),
                'mrp' => (float) ($data['mrp'] ?? 0),
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ],
        );

        $batch->increment('quantity_received', $quantity);

        app(StockMovementService::class)->record([
            'tenant_id' => $user?->tenant_id,
            'company_id' => $user?->company_id,
            'store_id' => $user?->store_id,
            'movement_date' => now()->toDateString(),
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'movement_type' => 'opening_stock',
            'quantity_in' => $quantity,
            'quantity_out' => 0,
            'source_type' => 'import',
            'source_id' => 0,
            'notes' => 'Opening stock import',
            'created_by' => $user?->id,
        ]);
    }

    private function requiredValue(array $data, string $key): string
    {
        $value = trim((string) ($data[$key] ?? ''));

        if ($value === '') {
            throw new \RuntimeException($key.' is required.');
        }

        return $value;
    }

    private function persistPreviewRows(ImportJob $job, Collection $rows): void
    {
        $rows->take(self::PREVIEW_ROW_LIMIT)->values()->each(function (array $row, int $index) use ($job) {
            ImportStagedRow::query()->create([
                'import_job_id' => $job->id,
                'row_number' => $index + 1,
                'raw_data' => $row,
                'status' => 'pending',
            ]);
        });
    }

    private function readRows(string $path, string $extension): Collection
    {
        if (in_array(strtolower($extension), ['xlsx', 'xls'], true)) {
            return (new FastExcel())->import($path)->map(fn ($row) => $this->normaliseRow((array) $row))->values();
        }

        $handle = fopen($path, 'rb');
        $headers = [];
        $rows = collect();
        $line = 0;

        while (($data = fgetcsv($handle)) !== false) {
            $line++;

            if ($line === 1) {
                $headers = array_map(fn ($header) => trim((string) $header), $data);
                continue;
            }

            $row = [];

            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $row[$header] = $data[$index] ?? null;
                }
            }

            if ($row !== []) {
                $rows->push($this->normaliseRow($row));
            }
        }

        fclose($handle);

        return $rows;
    }

    private function normaliseRow(array $row): array
    {
        $normalised = [];

        foreach ($row as $key => $value) {
            $name = strtolower(trim((string) $key));
            $name = preg_replace('/[^a-z0-9]+/', '_', $name);
            $normalised[trim((string) $name, '_')] = is_string($value) ? trim($value) : $value;
        }

        return $normalised;
    }
}
