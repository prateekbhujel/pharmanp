<?php

namespace App\Modules\Reports\Repositories;

use App\Core\Support\ApiResponse;
use App\Modules\Reports\Repositories\Interfaces\ReportRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportRepository implements ReportRepositoryInterface
{
    public function paginate(mixed $query, int $perPage): array
    {
        $page = $query->paginate($perPage);

        return [
            'data' => $page->items(),
            'meta' => ApiResponse::paginationMeta($page),
        ];
    }

    public function accountTransactionTotals(?int $tenantId, ?int $companyId, ?string $from = null, ?string $to = null, ?string $accountType = null): object
    {
        return $this->scopedAccountTransactions($tenantId, $companyId, $from, $to, $accountType)
            ->selectRaw('COALESCE(SUM(debit), 0) as debit_total, COALESCE(SUM(credit), 0) as credit_total')
            ->first() ?: (object) ['debit_total' => 0, 'credit_total' => 0];
    }

    public function accountTypeTotals(?int $tenantId, ?int $companyId, ?string $from = null, ?string $to = null): Collection
    {
        return $this->scopedAccountTransactions($tenantId, $companyId, $from, $to)
            ->selectRaw('account_type, SUM(debit) as debit_total, SUM(credit) as credit_total')
            ->groupBy('account_type')
            ->get()
            ->keyBy('account_type');
    }

    private function scopedAccountTransactions(?int $tenantId, ?int $companyId, ?string $from = null, ?string $to = null, ?string $accountType = null)
    {
        $query = DB::table('account_transactions')
            ->when($tenantId, fn ($builder, int $id) => $builder->where('tenant_id', $id))
            ->when($companyId, fn ($builder, int $id) => $builder->where('company_id', $id))
            ->when($accountType, fn ($builder, string $type) => $builder->where('account_type', $type));

        if ($from) {
            $query->whereDate('transaction_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('transaction_date', '<=', $to);
        }

        return $query;
    }
}
