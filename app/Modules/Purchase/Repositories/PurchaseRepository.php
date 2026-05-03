<?php

namespace App\Modules\Purchase\Repositories;

use App\Core\DTOs\TableQueryData;
use App\Core\Query\TableQueryApplier;
use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Product;
use App\Modules\Party\Models\Supplier;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Repositories\Interfaces\PurchaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PurchaseRepository implements PurchaseRepositoryInterface
{
    private const SORTS = [
        'purchase_no' => 'purchase_no',
        'purchase_date' => 'purchase_date',
        'grand_total' => 'grand_total',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function __construct(private readonly TableQueryApplier $tables) {}

    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        $query = Purchase::query()
            ->with('supplier:id,name');

        $this->tables->tenant($query, $user, 'tenant_id');

        $query
            ->when($table->search, function (Builder $builder, string $search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $this->tables->search($inner, $search, ['purchase_no', 'supplier_invoice_no']);
                    $inner->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($table->filters['supplier_id'] ?? null, fn (Builder $builder, mixed $supplierId) => $builder->where('supplier_id', $supplierId))
            ->when($table->filters['payment_status'] ?? null, fn (Builder $builder, mixed $status) => $builder->where('payment_status', $status))
            ->when($table->filters['from'] ?? null, fn (Builder $builder, mixed $from) => $builder->whereDate('purchase_date', '>=', $from))
            ->when($table->filters['to'] ?? null, fn (Builder $builder, mixed $to) => $builder->whereDate('purchase_date', '<=', $to));

        return $this->tables->paginate(
            $query
                ->orderBy($this->tables->sortColumn($table, self::SORTS, 'purchase_date'), $table->sortOrder)
                ->orderByDesc('id'),
            $table,
        );
    }

    public function createPurchase(array $data): Purchase
    {
        return Purchase::query()->create($data);
    }

    public function productForUpdate(int $id): Product
    {
        return Product::query()->lockForUpdate()->findOrFail($id);
    }

    public function batchForPurchase(int $companyId, int $productId, string $batchNo): ?Batch
    {
        return Batch::query()
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('batch_no', $batchNo)
            ->lockForUpdate()
            ->first();
    }

    public function createBatch(array $data): Batch
    {
        return Batch::query()->create($data);
    }

    public function saveBatch(Batch $batch, array $data): Batch
    {
        $batch->forceFill($data)->save();

        return $batch;
    }

    public function createItem(Purchase $purchase, array $data): PurchaseItem
    {
        return $purchase->items()->create($data);
    }

    public function saveProduct(Product $product, array $data): Product
    {
        $product->forceFill($data)->save();

        return $product;
    }

    public function incrementSupplierBalance(int $supplierId, float $amount): void
    {
        Supplier::query()->whereKey($supplierId)->increment('current_balance', $amount);
    }

    public function fresh(Purchase $purchase): Purchase
    {
        return $purchase->fresh(['supplier', 'items.product', 'items.batch']);
    }
}
