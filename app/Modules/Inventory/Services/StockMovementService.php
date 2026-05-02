<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Contracts\StockMovementServiceInterface;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockMovementService implements StockMovementServiceInterface
{
    public function record(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {
            $product = Product::query()->find($data['product_id'] ?? null);

            if (! $product) {
                throw ValidationException::withMessages(['product_id' => 'Product does not exist.']);
            }

            $batch = null;

            if (! empty($data['batch_id'])) {
                $batch = Batch::query()->lockForUpdate()->find($data['batch_id']);

                if (! $batch || (int) $batch->product_id !== (int) $product->id) {
                    throw ValidationException::withMessages(['batch_id' => 'Batch does not belong to the selected product.']);
                }

                $net = (float) ($data['quantity_in'] ?? 0) - (float) ($data['quantity_out'] ?? 0);
                $nextQuantity = (float) $batch->quantity_available + $net;

                if ($nextQuantity < 0) {
                    throw ValidationException::withMessages(['quantity_out' => 'Stock cannot go below zero.']);
                }

                $batch->forceFill(['quantity_available' => $nextQuantity])->save();
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
}
