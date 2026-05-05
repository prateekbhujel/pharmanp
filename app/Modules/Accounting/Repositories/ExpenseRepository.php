<?php

namespace App\Modules\Accounting\Repositories;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Core\Query\TableQueryApplier;
use App\Models\User;
use App\Modules\Accounting\Models\AccountTransaction;
use App\Modules\Accounting\Models\Expense;
use App\Modules\Accounting\Repositories\Interfaces\ExpenseRepositoryInterface;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ExpenseRepository implements ExpenseRepositoryInterface
{
    private const SORTS = [
        'expense_date' => 'expense_date',
        'category' => 'category',
        'vendor_name' => 'vendor_name',
        'amount' => 'amount',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function __construct(private readonly TableQueryApplier $tables) {}

    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        $query = Expense::query()
            ->with(['expenseCategory', 'paymentModeOption', 'creator']);

        $this->tables->tenant($query, $user, 'tenant_id');

        $query
            ->when($table->search, function (Builder $builder, string $search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $this->tables->search($inner, $search, ['category', 'vendor_name', 'notes']);
                    $inner->orWhereHas('expenseCategory', fn (Builder $category) => $category->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($table->filters['expense_category_id'] ?? null, fn (Builder $builder, mixed $id) => $builder->where('expense_category_id', $id))
            ->when($table->filters['payment_mode_id'] ?? null, fn (Builder $builder, mixed $id) => $builder->where('payment_mode_id', $id))
            ->when($table->filters['from'] ?? null, fn (Builder $builder, mixed $from) => $builder->where('expense_date', '>=', $from))
            ->when($table->filters['to'] ?? null, fn (Builder $builder, mixed $to) => $builder->where('expense_date', '<=', $to));

        return $this->tables->paginate(
            $query
                ->orderBy($this->tables->sortColumn($table, self::SORTS, 'expense_date'), $table->sortOrder)
                ->orderByDesc('id'),
            $table,
        );
    }

    public function summary(?User $user = null): array
    {
        $cashModeIds = $this->paymentModeIds('cash');
        $bankModeIds = $this->paymentModeIds('bank');
        $query = Expense::query()
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId));

        return [
            'this_month' => (clone $query)
                ->whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year)
                ->sum('amount'),
            'cash' => $cashModeIds->isNotEmpty()
                ? (clone $query)->whereIn('payment_mode_id', $cashModeIds)->sum('amount')
                : (clone $query)->where('payment_mode', 'cash')->sum('amount'),
            'bank' => $bankModeIds->isNotEmpty()
                ? (clone $query)->whereIn('payment_mode_id', $bankModeIds)->sum('amount')
                : (clone $query)->where('payment_mode', 'bank')->sum('amount'),
            'total' => (clone $query)->sum('amount'),
        ];
    }

    public function lookups(): array
    {
        return [
            'expense_categories' => DropdownOption::query()->forAlias('expense_category')->active()->orderBy('name')->get(['id', 'name']),
            'payment_modes' => DropdownOption::query()->forAlias('payment_mode')->active()->orderBy('name')->get(['id', 'name', 'data']),
        ];
    }

    public function category(int $id): DropdownOption
    {
        return DropdownOption::query()->forAlias('expense_category')->findOrFail($id);
    }

    public function paymentMode(int $id): DropdownOption
    {
        return DropdownOption::query()->forAlias('payment_mode')->findOrFail($id);
    }

    public function findForUpdate(?int $id = null): Expense
    {
        return $id ? Expense::query()->lockForUpdate()->findOrFail($id) : new Expense;
    }

    public function deleteTransactions(Expense $expense): void
    {
        AccountTransaction::query()
            ->where('source_type', 'Expense')
            ->where('source_id', $expense->id)
            ->delete();
    }

    public function createTransaction(array $data): void
    {
        AccountTransaction::query()->create($data);
    }

    public function save(Expense $expense, array $payload): Expense
    {
        $expense->fill($payload)->save();

        return $expense;
    }

    public function delete(Expense $expense): void
    {
        $expense->delete();
    }

    public function paymentModeIds(string $mode): Collection
    {
        return DropdownOption::query()->forAlias('payment_mode')->where('data', $mode)->pluck('id');
    }
}
