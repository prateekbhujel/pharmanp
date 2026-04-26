<?php

namespace App\Modules\Inventory\Services;

use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use Illuminate\Support\Facades\DB;

class BatchService
{
    public function __construct(private readonly StockMovementService $stock) {}

    public function save(array $data, User $user, ?Batch $batch = null): Batch
    {
        return DB::transaction(function () use ($data, $user, $batch) {
            $quantityAvailable = (float) ($data['quantity_available'] ?? $data['quantity_received']);
            $oldQuantity = $batch ? (float) $batch->quantity_available : 0;
            $batch ??= new Batch();

            $batch->fill([
                'tenant_id' => $batch->tenant_id ?: $user->tenant_id,
                'company_id' => $batch->company_id ?: $user->company_id,
                'store_id' => $batch->store_id ?: $user->store_id,
                'product_id' => $data['product_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'batch_no' => $data['batch_no'],
                'barcode' => $data['barcode'] ?? null,
                'storage_location' => $data['storage_location'] ?? null,
                'manufactured_at' => $data['manufactured_at'] ?? null,
                'expires_at' => $data['expires_at'],
                'quantity_received' => $data['quantity_received'],
                'quantity_available' => $oldQuantity,
                'purchase_price' => $data['purchase_price'],
                'mrp' => $data['mrp'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
                'updated_by' => $user->id,
            ]);

            if (! $batch->exists) {
                $batch->created_by = $user->id;
            }

            $batch->save();

            $diff = round($quantityAvailable - $oldQuantity, 3);
            if ($diff !== 0.0) {
                $this->stock->record([
                    'tenant_id' => $batch->tenant_id,
                    'company_id' => $batch->company_id,
                    'store_id' => $batch->store_id,
                    'movement_date' => now()->toDateString(),
                    'product_id' => $batch->product_id,
                    'batch_id' => $batch->id,
                    'movement_type' => $diff > 0 ? 'manual_batch_in' : 'manual_batch_out',
                    'quantity_in' => $diff > 0 ? abs($diff) : 0,
                    'quantity_out' => $diff < 0 ? abs($diff) : 0,
                    'source_type' => 'batch',
                    'source_id' => $batch->id,
                    'reference_type' => 'batch',
                    'reference_id' => $batch->id,
                    'notes' => $batch->wasRecentlyCreated ? 'Manual batch entry.' : 'Manual batch quantity update.',
                    'created_by' => $user->id,
                ]);
            }

            return $batch->fresh(['product.company', 'supplier']);
        });
    }

    public function delete(Batch $batch, User $user): void
    {
        DB::transaction(function () use ($batch, $user) {
            $batch->forceFill([
                'is_active' => false,
                'updated_by' => $user->id,
            ])->save();

            $batch->delete();
        });
    }
}
