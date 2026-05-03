<?php

namespace App\Modules\Reports\Repositories\Interfaces;

use Illuminate\Support\Collection;

interface ReportRepositoryInterface
{
    public function paginate(mixed $query, int $perPage): array;

    public function accountTransactionTotals(?int $tenantId, ?int $companyId, ?string $from = null, ?string $to = null, ?string $accountType = null): object;

    public function accountTypeTotals(?int $tenantId, ?int $companyId, ?string $from = null, ?string $to = null): Collection;
}
