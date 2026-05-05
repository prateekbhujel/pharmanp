<?php

namespace App\Modules\Purchase\Repositories;

use App\Core\DTOs\TableQueryData;
use App\Core\Query\TableQueryApplier;
use App\Core\Security\TenantRecordScope;
use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Party\Models\Supplier;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Models\PurchaseReturn;
use App\Modules\Purchase\Models\PurchaseReturnItem;
use App\Modules\Purchase\Repositories\Interfaces\PurchaseReturnRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PurchaseReturnRepository implements PurchaseReturnRepositoryInterface
{
    private const SORTS = [
        'return_no' => 'return_no',
        'return_date' => 'return_date',
        'return_type' => 'return_type',
        'grand_total' => 'grand_total',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function __construct(
        private readonly TableQueryApplier $tables,
        private readonly TenantRecordScope $scope,
    ) {}

    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        $query = PurchaseReturn::query()
            ->with(['supplier:id,name', 'purchase:id,purchase_no,supplier_invoice_no'])
            ->withCount('items');

        $this->tables->tenant($query, $user, 'tenant_id');
        $this->tables->softDeletes($query, $table);

        $query
            ->when($table->search, function (Builder $builder, string $search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $this->tables->search($inner, $search, ['return_no', 'notes']);
                    $inner
                        ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('purchase', fn (Builder $purchase) => $purchase
                            ->where('purchase_no', 'like', '%'.$search.'%')
                            ->orWhere('supplier_invoice_no', 'like', '%'.$search.'%'));
                });
            })
            ->when($table->filters['supplier_id'] ?? null, fn (Builder $builder, mixed $supplierId) => $builder->where('supplier_id', $supplierId))
            ->when($table->filters['return_type'] ?? null, fn (Builder $builder, mixed $type) => $builder->where('return_type', $type))
            ->when(($table->filters['return_mode'] ?? null) === 'bill', fn (Builder $builder) => $builder->whereNotNull('purchase_id'))
            ->when(in_array($table->filters['return_mode'] ?? null, ['manual', 'product'], true), fn (Builder $builder) => $builder->whereNull('purchase_id'))
            ->when($table->filters['from'] ?? null, fn (Builder $builder, mixed $from) => $builder->whereDate('return_date', '>=', $from))
            ->when($table->filters['to'] ?? null, fn (Builder $builder, mixed $to) => $builder->whereDate('return_date', '<=', $to));

        return $this->tables->paginate(
            $query
                ->orderBy($this->tables->sortColumn($table, self::SORTS, 'return_date'), $table->sortOrder)
                ->orderByDesc('id'),
            $table,
        );
    }

    public function purchase(int $id, ?User $user = null): Purchase
    {
        return $this->scope->apply(Purchase::query(), $user, ['store' => null])->findOrFail($id);
    }

    public function batchForUpdate(int $id, ?User $user = null, ?PurchaseReturn $purchaseReturn = null): Batch
    {
        return $this->scope->apply(Batch::query(), $user)
            ->when($purchaseReturn?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($purchaseReturn?->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->when($purchaseReturn?->store_id, fn (Builder $builder, int $storeId) => $builder->where('store_id', $storeId))
            ->lockForUpdate()
            ->findOrFail($id);
    }

    public function purchaseItem(int $purchaseId, int $purchaseItemId, ?User $user = null): PurchaseItem
    {
        return PurchaseItem::query()
            ->where('purchase_id', $purchaseId)
            ->findOrFail($purchaseItemId);
    }

    public function returnedQuantityForItem(int $purchaseItemId, int $excludingReturnId): float
    {
        return (float) PurchaseReturnItem::query()
            ->where('purchase_item_id', $purchaseItemId)
            ->where('purchase_return_id', '<>', $excludingReturnId)
            ->sum('return_qty');
    }

    public function createItem(array $data): PurchaseReturnItem
    {
        return PurchaseReturnItem::query()->create($data);
    }

    public function deleteItems(PurchaseReturn $purchaseReturn): void
    {
        PurchaseReturnItem::query()->where('purchase_return_id', $purchaseReturn->id)->delete();
    }

    public function delete(PurchaseReturn $purchaseReturn): void
    {
        $purchaseReturn->delete();
    }

    public function save(PurchaseReturn $purchaseReturn, array $data): PurchaseReturn
    {
        $purchaseReturn->forceFill($data)->save();

        return $purchaseReturn;
    }

    public function adjustSupplierBalance(int $supplierId, float $amount, ?User $user = null, ?PurchaseReturn $purchaseReturn = null): void
    {
        $supplier = $this->scope->apply(Supplier::query(), $user, ['store' => null])
            ->when($purchaseReturn?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($purchaseReturn?->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->findOrFail($supplierId);

        $supplier->increment('current_balance', $amount);
    }

    public function fresh(PurchaseReturn $purchaseReturn): PurchaseReturn
    {
        return $purchaseReturn->fresh(['supplier', 'purchase', 'items.product', 'items.batch']);
    }
}
