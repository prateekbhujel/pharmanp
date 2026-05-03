<?php

namespace App\Modules\Sales\Repositories;

use App\Core\DTOs\TableQueryData;
use App\Core\Query\TableQueryApplier;
use App\Models\User;
use App\Modules\Accounting\Models\AccountTransaction;
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

    public function __construct(private readonly TableQueryApplier $tables) {}

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

    public function invoice(?int $id): ?SalesInvoice
    {
        return $id ? SalesInvoice::query()->findOrFail($id) : null;
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

    public function deleteTransactions(SalesReturn $salesReturn): void
    {
        AccountTransaction::query()
            ->whereIn('source_type', ['SalesReturn', 'sales_return'])
            ->where('source_id', $salesReturn->id)
            ->delete();
    }

    public function createTransaction(array $payload): AccountTransaction
    {
        return AccountTransaction::query()->create($payload);
    }

    public function delete(SalesReturn $salesReturn): void
    {
        $salesReturn->delete();
    }

    public function nextReturnNo(): string
    {
        return 'SR-'.str_pad((string) (SalesReturn::withTrashed()->count() + 1), 5, '0', STR_PAD_LEFT);
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
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($filters['customer_id'] ?? null, fn (Builder $builder, mixed $customerId) => $builder->where('customer_id', $customerId))
            ->when($filters['q'] ?? null, fn (Builder $builder, mixed $keyword) => $builder->where('invoice_no', 'like', '%'.$keyword.'%'))
            ->latest('invoice_date')
            ->limit(50)
            ->get();
    }
}
