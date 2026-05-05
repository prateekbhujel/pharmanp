<?php

namespace App\Modules\Sales\Repositories;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Core\Query\TableQueryApplier;
use App\Core\Security\TenantRecordScope;
use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Product;
use App\Modules\Party\Models\Customer;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesReturn;
use App\Modules\Sales\Models\SalesReturnItem;
use App\Modules\Sales\Repositories\Interfaces\SalesReturnRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SalesReturnRepository implements SalesReturnRepositoryInterface
{
    private const SORTS = [
        'return_date' => 'return_date',
        'return_no' => 'return_no',
        'return_type' => 'return_type',
        'total_amount' => 'total_amount',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function __construct(
        private readonly TableQueryApplier $tables,
        private readonly TenantRecordScope $scope,
    ) {}

    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        $query = SalesReturn::query()
            ->with(['invoice', 'customer', 'items.product']);

        $this->tables->tenant($query, $user, 'tenant_id');
        $this->tables->softDeletes($query, $table);

        $query
            ->when($table->search, function (Builder $builder, string $search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $this->tables->search($inner, $search, ['return_no', 'reason']);
                    $inner
                        ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('invoice', fn (Builder $invoice) => $invoice->where('invoice_no', 'like', '%'.$search.'%'));
                });
            })
            ->when($table->filters['from'] ?? null, fn (Builder $builder, mixed $from) => $builder->where('return_date', '>=', $from))
            ->when($table->filters['to'] ?? null, fn (Builder $builder, mixed $to) => $builder->where('return_date', '<=', $to))
            ->when($table->filters['customer_id'] ?? null, fn (Builder $builder, mixed $customerId) => $builder->where('customer_id', $customerId))
            ->when($table->filters['return_type'] ?? null, fn (Builder $builder, mixed $type) => $builder->where('return_type', $type));

        return $this->tables->paginate(
            $query
                ->orderBy($this->tables->sortColumn($table, self::SORTS, 'return_date'), $table->sortOrder)
                ->orderByDesc('id'),
            $table,
        );
    }

    public function invoice(?int $id, ?User $user = null): ?SalesInvoice
    {
        return $id ? $this->scope->apply(SalesInvoice::query(), $user, ['store' => null])->findOrFail($id) : null;
    }

    public function customer(int $id, ?User $user = null): Customer
    {
        return $this->scope->apply(Customer::query(), $user, ['store' => null])->findOrFail($id);
    }

    public function product(int $id, ?User $user = null): Product
    {
        return $this->scope->apply(Product::query(), $user, ['store' => null])->findOrFail($id);
    }

    public function batch(?int $id, int $productId, ?User $user = null, ?SalesReturn $salesReturn = null): ?Batch
    {
        if (! $id) {
            return null;
        }

        return $this->scope->apply(Batch::query(), $user)
            ->when($salesReturn?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($salesReturn?->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->when($salesReturn?->store_id, fn (Builder $builder, int $storeId) => $builder->where('store_id', $storeId))
            ->where('product_id', $productId)
            ->findOrFail($id);
    }

    public function save(SalesReturn $salesReturn, array $payload): SalesReturn
    {
        $salesReturn->forceFill($payload)->save();

        return $salesReturn;
    }

    public function createItem(SalesReturn $salesReturn, array $payload): SalesReturnItem
    {
        return $salesReturn->items()->create($payload);
    }

    public function deleteItems(SalesReturn $salesReturn): void
    {
        SalesReturnItem::query()->where('sales_return_id', $salesReturn->id)->delete();
    }

    public function delete(SalesReturn $salesReturn): void
    {
        $salesReturn->delete();
    }

    public function fresh(SalesReturn $salesReturn): SalesReturn
    {
        return $salesReturn->fresh(['invoice', 'customer', 'items.product', 'items.batch']);
    }

    public function invoiceOptions(array $filters = [], ?User $user = null): Collection
    {
        return SalesInvoice::query()
            ->with('customer')
            ->where('status', 'confirmed')
            ->when(! $user?->canAccessAllTenants() && $user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when(! $user?->canAccessAllTenants() && $user?->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->when(! $user?->canAccessAllTenants() && $user?->store_id, fn (Builder $builder, int $storeId) => $builder->where('store_id', $storeId))
            ->when($filters['customer_id'] ?? null, fn (Builder $builder, mixed $customerId) => $builder->where('customer_id', $customerId))
            ->when($filters['q'] ?? null, fn (Builder $builder, mixed $keyword) => $builder->where('invoice_no', 'like', '%'.$keyword.'%'))
            ->latest('invoice_date')
            ->limit(50)
            ->get();
    }
}
