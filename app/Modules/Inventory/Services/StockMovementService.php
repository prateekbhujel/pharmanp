<?php

namespace App\Modules\Inventory\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use App\Core\Utils\Math;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    public function record(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {
            $product = Product::query()->lockForUpdate()->find($data['product_id'] ?? null);

            if (! $product) {
                throw ValidationException::withMessages(['product_id' => 'Product does not exist.']);
            }

            $this->assertContextMatches('product_id', $product, $data);

            $batch = null;

            if (! empty($data['batch_id'])) {
                $batch = Batch::query()->lockForUpdate()->find($data['batch_id']);

                if (! $batch || (int) $batch->product_id !== (int) $product->id) {
                    throw ValidationException::withMessages(['batch_id' => 'Batch does not belong to the selected product.']);
                }

                $this->assertContextMatches('batch_id', $batch, $data);

                $net = Math::sub((string)($data['quantity_in'] ?? 0), (string)($data['quantity_out'] ?? 0));
                $nextQuantity = Math::add((string)$batch->quantity_available, $net);

                if (Math::sub($nextQuantity, '0') < 0) {
                    throw ValidationException::withMessages(['quantity_out' => 'Stock cannot go below zero.']);
                }

                $batch->forceFill(['quantity_available' => (float)$nextQuantity])->save();
            }

            return StockMovement::query()->create([
                'tenant_id' => $data['tenant_id'] ?? null,
                'company_id' => $data['company_id'] ?? $product->company_id,
                'store_id' => $data['store_id'] ?? $product->store_id,
                'movement_date' => $data['movement_date'] ?? now()->toDateString(),
                'product_id' => $product->id,
                'batch_id' => $batch?->id,
                'movement_type' => $data['movement_type'],
                'quantity_in' => $data['quantity_in'] ?? 0,
                'quantity_out' => $data['quantity_out'] ?? 0,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);
        });
    }

    private function assertContextMatches(string $field, Model $record, array $data): void
    {
        foreach (['tenant_id', 'company_id', 'store_id'] as $column) {
            if (! array_key_exists($column, $data) || $data[$column] === null || $record->getAttribute($column) === null) {
                continue;
            }

            if ((int) $data[$column] !== (int) $record->getAttribute($column)) {
                throw ValidationException::withMessages([
                    $field => 'Selected stock record does not belong to this operating context.',
                ]);
            }
        }
    }
}
