<?php

namespace App\Modules\Setup\Repositories\Interfaces;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Setup\Models\FiscalYear;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FiscalYearRepositoryInterface
{
    public function paginate(TableQueryData $table, int $companyId): LengthAwarePaginator;

    public function save(FiscalYear $fiscalYear, array $data, User $user, int $companyId): FiscalYear;

    public function delete(FiscalYear $fiscalYear): void;

    public function replacement(int $companyId): ?FiscalYear;

    public function clearCurrent(int $companyId, ?FiscalYear $except = null): void;

    public function markCurrent(FiscalYear $fiscalYear): void;
}
