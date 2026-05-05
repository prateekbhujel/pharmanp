<?php

namespace App\Modules\Accounting\Repositories\Interfaces;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Accounting\Models\Expense;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ExpenseRepositoryInterface
{
    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator;

    public function summary(?User $user = null): array;

    public function lookups(): array;

    public function category(int $id): DropdownOption;

    public function paymentMode(int $id): DropdownOption;

    public function findForUpdate(?int $id = null): Expense;

    public function deleteTransactions(Expense $expense): void;

    public function createTransaction(array $data): void;

    public function save(Expense $expense, array $payload): Expense;

    public function delete(Expense $expense): void;

    public function paymentModeIds(string $mode): Collection;
}
