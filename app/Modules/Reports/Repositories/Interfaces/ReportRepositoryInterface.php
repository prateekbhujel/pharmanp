<?php

namespace App\Modules\Reports\Repositories\Interfaces;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

interface ReportRepositoryInterface
{
    public function paginate(mixed $query, int $perPage): array;

    public function salesQuery(User $user, ?string $from = null, ?string $to = null, array $filters = []): Builder;

    public function purchaseQuery(User $user, ?string $from = null, ?string $to = null, array $filters = []): Builder;

    public function stockQuery(User $user, array $filters = []): Builder;

    public function lowStockQuery(User $user, array $filters = []): Builder;

    public function expiryQuery(User $user, ?string $from = null, ?string $to = null, array $filters = []): Builder;

    public function supplierPerformanceQuery(User $user, ?string $from = null, ?string $to = null): Builder;

    public function accountBookQuery(User $user, ?string $from = null, ?string $to = null, ?string $accountType = null, array $filters = []): Builder;

    public function supplierLedgerQuery(User $user, int $supplierId, ?string $from = null, ?string $to = null): Builder;

    public function customerLedgerQuery(User $user, int $customerId, ?string $from = null, ?string $to = null): Builder;

    public function productMovementQuery(User $user, int $productId, ?string $from = null, ?string $to = null): Builder;

    public function accountTransactionTotals(?int $tenantId, ?int $companyId, ?string $from = null, ?string $to = null, ?string $accountType = null): object;

    public function accountTypeTotals(?int $tenantId, ?int $companyId, ?string $from = null, ?string $to = null): Collection;
}
