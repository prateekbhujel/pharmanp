<?php

namespace App\Modules\Setup\Repositories;

use App\Modules\Accounting\Models\Expense;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Setup\Models\DropdownOption;
use App\Modules\Setup\Repositories\Interfaces\DropdownOptionRepositoryInterface;
use Illuminate\Support\Collection;

class DropdownOptionRepository implements DropdownOptionRepositoryInterface
{
    public function managed(?string $alias = null): Collection
    {
        return DropdownOption::query()
            ->whereIn('alias', array_keys(DropdownOption::managedAliases()))
            ->when($alias, fn ($query, string $value) => $query->where('alias', $value))
            ->orderBy('alias')
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): DropdownOption
    {
        return DropdownOption::query()->create($data);
    }

    public function update(DropdownOption $option, array $data): DropdownOption
    {
        $option->update($data);

        return $option->fresh();
    }

    public function updateStatus(DropdownOption $option, bool $active): DropdownOption
    {
        $option->update(['status' => (int) $active]);

        return $option->fresh();
    }

    public function delete(DropdownOption $option): void
    {
        $option->delete();
    }

    public function linkedUsageCount(DropdownOption $option): int
    {
        return match ($option->alias) {
            'expense_category' => Expense::query()->where('expense_category_id', $option->id)->count(),
            'payment_mode' => Expense::query()->where('payment_mode_id', $option->id)->count()
                + Payment::query()->where('payment_mode_id', $option->id)->count(),
            default => 0,
        };
    }
}
