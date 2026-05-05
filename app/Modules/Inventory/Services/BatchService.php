<?php

namespace App\Modules\Inventory\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BatchService
{
    public function __construct(private readonly StockMovementService $stock) {}

    public function table(Request $request, User $user): array
    {
        $sorts = [
            'batch_no' => 'batch_no',
            'expires_at' => 'expires_at',
            'quantity_available' => 'quantity_available',
            'purchase_price' => 'purchase_price',
            'mrp' => 'mrp',
            'created_at' => 'created_at',
        ];
        $sortField = $sorts[$request->query('sort_field', 'expires_at')] ?? 'expires_at';
        $sortOrder = $request->query('sort_order') === 'desc' ? 'desc' : 'asc';
        $search = trim((string) $request->query('search'));

        $query = Batch::query()
            ->with(['product.company', 'supplier'])
            ->when($user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('batch_no', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%')
                        ->orWhere('storage_location', 'like', '%'.$search.'%')
                        ->orWhereHas('product', fn (Builder $product) => $product
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('generic_name', 'like', '%'.$search.'%')
                            ->orWhere('sku', 'like', '%'.$search.'%'))
                        ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->filled('product_id'), fn (Builder $builder) => $builder->where('product_id', $request->integer('product_id')))
            ->when($request->filled('supplier_id'), fn (Builder $builder) => $builder->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('expiry_status'), fn (Builder $builder) => $this->expiryFilter($builder, (string) $request->query('expiry_status')))
            ->when($request->filled('from'), fn (Builder $builder) => $builder->whereDate('expires_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn (Builder $builder) => $builder->whereDate('expires_at', '<=', $request->query('to')))
            ->when($request->filled('is_active'), fn (Builder $builder) => $builder->where('is_active', $request->boolean('is_active')))
            ->orderBy($sortField, $sortOrder)
            ->orderBy('id');

        $summaryQuery = clone $query;

        return [
            'batches' => $query->paginate(min(100, max(5, $request->integer('per_page', 15)))),
            'summary' => [
                'total_batches' => (clone $summaryQuery)->count(),
                'total_stock' => (float) (clone $summaryQuery)->sum('quantity_available'),
                'expired_batches' => (clone $summaryQuery)->whereDate('expires_at', '<', today())->count(),
                'expiring_30' => (clone $summaryQuery)->whereBetween('expires_at', [today(), today()->addDays(30)])->count(),
            ],
        ];
    }

    public function options(Request $request, User $user): Collection
    {
        return Batch::query()
            ->with('product:id,name')
            ->when($user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->where('quantity_available', '>', 0)
            ->when($request->filled('product_id'), fn (Builder $builder) => $builder->where('product_id', $request->integer('product_id')))
            ->when($request->filled('supplier_id'), fn (Builder $builder) => $builder->where('supplier_id', $request->integer('supplier_id')))
            ->orderBy('expires_at')
            ->orderBy('batch_no')
            ->limit(100)
            ->get();
    }

    public function save(array $data, User $user, ?Batch $batch = null): Batch
    {
        return DB::transaction(function () use ($data, $user, $batch) {
            $quantityAvailable = (float) ($data['quantity_available'] ?? $data['quantity_received']);
            $oldQuantity = $batch ? (float) $batch->quantity_available : 0;
            $batch ??= new Batch;

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

    private function expiryFilter(Builder $builder, string $status): void
    {
        if ($status === 'expired') {
            $builder->whereDate('expires_at', '<', today());
        } elseif ($status === '30d') {
            $builder->whereBetween('expires_at', [today(), today()->addDays(30)]);
        } elseif ($status === '60d') {
            $builder->whereBetween('expires_at', [today(), today()->addDays(60)]);
        } elseif ($status === 'available') {
            $builder->where('quantity_available', '>', 0);
        }
    }
}
