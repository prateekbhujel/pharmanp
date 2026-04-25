<?php

namespace App\Modules\ImportExport\Services;

use App\Modules\ImportExport\Models\ImportJob;
use App\Modules\ImportExport\Models\ImportStagedRow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;

class ImportPreviewService
{
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
        $path = $file->store('imports/staged');
        $rows = $this->readRows(Storage::disk('local')->path($path), $file->getClientOriginalExtension());
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

            $rows->take(25)->values()->each(function (array $row, int $index) use ($job) {
                ImportStagedRow::query()->create([
                    'import_job_id' => $job->id,
                    'row_number' => $index + 1,
                    'raw_data' => $row,
                    'status' => 'pending',
                ]);
            });

            return $job->fresh('rows');
        });
    }

    public function confirm(int $jobId, array $mapping): ImportJob
    {
        return DB::transaction(function () use ($jobId, $mapping) {
            $job = ImportJob::query()->lockForUpdate()->findOrFail($jobId);
            $required = $this->requiredFields($job->target);
            $mappedSystemFields = array_values(array_filter($mapping));
            $missing = array_values(array_diff($required, $mappedSystemFields));

            $job->update([
                'mapping' => $mapping,
                'valid_rows' => empty($missing) ? (int) $job->total_rows : 0,
                'invalid_rows' => empty($missing) ? 0 : (int) $job->total_rows,
                'status' => empty($missing) ? 'validated' : 'needs_mapping',
            ]);

            $job->rows()->update([
                'errors' => empty($missing) ? null : ['Missing required mapping: '.implode(', ', $missing)],
                'status' => empty($missing) ? 'valid' : 'invalid',
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

    private function readRows(string $path, string $extension): Collection
    {
        if (in_array(strtolower($extension), ['xlsx', 'xls'], true)) {
            return (new FastExcel())->import($path)->take(100)->map(fn ($row) => $this->normaliseRow((array) $row))->values();
        }

        $handle = fopen($path, 'rb');
        $headers = [];
        $rows = collect();
        $line = 0;

        while (($data = fgetcsv($handle)) !== false && $rows->count() < 100) {
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
