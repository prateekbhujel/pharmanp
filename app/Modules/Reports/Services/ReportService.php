<?php

namespace App\Modules\Reports\Services;

use App\Core\DTOs\TableQueryData;
use App\Core\Support\ApiResponse;
use App\Core\Support\MoneyAmount;
use App\Modules\Accounting\Support\AccountCatalog;
use App\Modules\Analytics\Services\PharmaSignalService;
use App\Modules\MR\Services\MrPerformanceService;
use App\Modules\Reports\DTOs\ReportQueryData;
use App\Modules\Reports\Repositories\Interfaces\ReportRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public function __construct(
        private readonly MrPerformanceService $mrPerformance,
        private readonly PharmaSignalService $signals,
        private readonly AgingReportService $aging,
        private readonly ExpiryReportService $expiryBuckets,
        private readonly DumpingReportService $dumping,
        private readonly TargetAchievementService $targets,
        private readonly PerformanceReportService $performance,
        private readonly ReportRepositoryInterface $reports,
    ) {}

    public function run(string $report, Request $request, int $maxPerPage = TableQueryData::MAX_PER_PAGE): array
    {
        $query = ReportQueryData::fromRequest($request, $maxPerPage);

        return match ($report) {
            'sales' => $this->sales($request, $query->from, $query->to, $query->perPage),
            'purchase' => $this->purchase($request, $query->from, $query->to, $query->perPage),
            'stock' => $this->stock($request, $query->perPage),
            'low-stock' => $this->lowStock($request, $query->perPage),
            'expiry' => $this->expiry($request, $query->from, $query->to, $query->perPage),
            'expiry-buckets' => $this->expiryBuckets->buckets($request, $query->perPage),
            'dumping' => $this->dumping->slowMoving($request, $query->perPage),
            'smart-inventory' => $this->signals->inventorySignals($request, $query->perPage),
            'day-book' => $this->accountBook($request, $query->from, $query->to, $query->perPage),
            'cash-book' => $this->accountBook($request, $query->from, $query->to, $query->perPage, 'cash'),
            'bank-book' => $this->accountBook($request, $query->from, $query->to, $query->perPage, 'bank'),
            'ledger' => $this->accountLedger($request, $request->query('account_type'), $query->from, $query->to, $query->perPage),
            'trial-balance' => $this->trialBalance($request, $query->from, $query->to, $query->perPage),
            'account-tree' => $this->accountTree($request, $query->from, $query->to, $query->perPage),
            'profit-loss' => $this->profitLoss($request, $query->from, $query->to, $query->perPage),
            'supplier-performance' => $this->supplierPerformance($request, $query->from, $query->to, $query->perPage),
            'supplier-aging' => $this->aging->suppliers($request, $query->perPage),
            'customer-aging', 'sales-party-aging' => $this->aging->customers($request, $query->perPage),
            'supplier-ledger' => $this->supplierLedger($request, (int) $request->query('supplier_id'), $query->from, $query->to, $query->perPage),
            'customer-ledger' => $this->customerLedger($request, (int) $request->query('customer_id'), $query->from, $query->to, $query->perPage),
            'product-movement' => $this->productMovement($request, (int) $request->query('product_id'), $query->from, $query->to, $query->perPage),
            'mr-performance' => $this->mrPerformance($request),
            'target-achievement' => $this->targets->achievement($request, $query->perPage),
            'mr-vs-product' => $this->performance->mrVsProduct($request, $query->perPage),
            'mr-vs-division' => $this->performance->mrVsDivision($request, $query->perPage),
            'mr-vs-sales' => $this->performance->mrVsSales($request, $query->perPage),
            'company-vs-customer' => $this->performance->companyVsCustomer($request, $query->perPage),
            default => throw ValidationException::withMessages(['report' => 'Unknown report.']),
        };
    }

    private function sales(Request $request, ?string $from, ?string $to, int $perPage): array
    {
        return $this->paged($this->reports->salesQuery($request->user(), $from, $to, [
            'customer_id' => $request->query('customer_id'),
            'payment_status' => $request->query('payment_status'),
            'medical_representative_id' => $request->query('medical_representative_id'),
        ]), $perPage);
    }

    private function accountBook(Request $request, ?string $from, ?string $to, int $perPage, ?string $accountType = null): array
    {
        $page = $this->paged($this->reports->accountBookQuery($request->user(), $from, $to, $accountType, [
            'party_type' => $request->query('party_type'),
            'party_id' => $request->query('party_id'),
        ]), $perPage);
        $labels = AccountCatalog::labels();

        $page['data'] = collect($page['data'])->map(function ($row) use ($labels) {
            $row->account_label = $labels[$row->account_type] ?? $row->account_type;

            return $row;
        })->all();

        $totals = $this->reports->accountTransactionTotals($request->user()?->tenant_id, $request->user()?->company_id, $from, $to, $accountType, $request->user()?->store_id);
        $page['summary'] = [
            'debit' => MoneyAmount::decimal($totals->debit_total),
            'credit' => MoneyAmount::decimal($totals->credit_total),
        ];

        return $page;
    }

    private function accountLedger(Request $request, ?string $accountType, ?string $from, ?string $to, int $perPage): array
    {
        if (! $accountType) {
            throw ValidationException::withMessages(['account_type' => 'Account type is required for ledger.']);
        }

        return $this->accountBook($request, $from, $to, $perPage, $accountType);
    }

    private function purchase(Request $request, ?string $from, ?string $to, int $perPage): array
    {
        return $this->paged($this->reports->purchaseQuery($request->user(), $from, $to, [
            'supplier_id' => $request->query('supplier_id'),
            'payment_status' => $request->query('payment_status'),
        ]), $perPage);
    }

    private function supplierLedger(Request $request, int $supplierId, ?string $from, ?string $to, int $perPage): array
    {
        if ($supplierId < 1) {
            throw ValidationException::withMessages(['supplier_id' => 'Supplier is required for supplier ledger.']);
        }

        return $this->paged($this->reports->supplierLedgerQuery($request->user(), $supplierId, $from, $to), $perPage);
    }

    private function stock(Request $request, int $perPage): array
    {
        return $this->paged($this->reports->stockQuery($request->user(), [
            'company_id' => $request->query('company_id'),
            'division_id' => $request->query('division_id'),
        ]), $perPage);
    }

    private function lowStock(Request $request, int $perPage): array
    {
        return $this->paged($this->reports->lowStockQuery($request->user(), [
            'company_id' => $request->query('company_id'),
            'division_id' => $request->query('division_id'),
        ]), $perPage);
    }

    private function expiry(Request $request, ?string $from, ?string $to, int $perPage): array
    {
        return $this->paged($this->reports->expiryQuery($request->user(), $from, $to, [
            'product_id' => $request->query('product_id'),
        ]), $perPage);
    }

    private function supplierPerformance(Request $request, ?string $from, ?string $to, int $perPage): array
    {
        return $this->paged($this->reports->supplierPerformanceQuery($request->user(), $from, $to), $perPage);
    }

    private function trialBalance(Request $request, ?string $from, ?string $to, int $perPage): array
    {
        $summary = $this->reports->accountTypeTotals($request->user()?->tenant_id, $request->user()?->company_id, $from, $to, $request->user()?->store_id);

        $rows = collect(AccountCatalog::all())
            ->map(function (array $account) use ($summary) {
                $totals = $summary->get($account['key']);
                $debit = MoneyAmount::decimal($totals?->debit_total ?? 0);
                $credit = MoneyAmount::decimal($totals?->credit_total ?? 0);
                $closing = AccountCatalog::closingBalance($debit, $credit, $account['nature']);

                return [
                    'code' => $account['code'],
                    'account' => $account['name'],
                    'group' => $account['group'],
                    'nature' => strtoupper($account['nature']),
                    'debit' => $debit,
                    'credit' => $credit,
                    'closing_amount' => $closing['amount'],
                    'closing_side' => $closing['side'],
                ];
            })
            ->filter(fn (array $row) => MoneyAmount::cents($row['debit']) > 0 || MoneyAmount::cents($row['credit']) > 0)
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = $rows->forPage($page, $perPage)->values();
        $paginator = new LengthAwarePaginator($items, $rows->count(), $perPage, $page);

        return [
            'data' => $paginator->items(),
            'meta' => ApiResponse::paginationMeta($paginator),
            'summary' => [
                'debit' => $this->sumMoney($rows, 'debit'),
                'credit' => $this->sumMoney($rows, 'credit'),
                'difference' => MoneyAmount::absoluteDifference($this->sumMoney($rows, 'debit'), $this->sumMoney($rows, 'credit')),
            ],
        ];
    }

    private function accountTree(Request $request, ?string $from, ?string $to, int $perPage): array
    {
        $summary = $this->reports->accountTypeTotals($request->user()?->tenant_id, $request->user()?->company_id, $from, $to, $request->user()?->store_id);
        $rows = collect(AccountCatalog::all())
            ->map(function (array $account) use ($summary) {
                $totals = $summary->get($account['key']);
                $debit = MoneyAmount::decimal($totals?->debit_total ?? 0);
                $credit = MoneyAmount::decimal($totals?->credit_total ?? 0);
                $closing = AccountCatalog::closingBalance($debit, $credit, $account['nature']);

                return [
                    'code' => $account['code'],
                    'account' => $account['name'],
                    'account_key' => $account['key'],
                    'group' => $account['group'],
                    'normal_side' => strtoupper($account['nature']),
                    'debit' => $debit,
                    'credit' => $credit,
                    'closing_amount' => $closing['amount'],
                    'closing_side' => $closing['side'],
                ];
            })
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = $rows->forPage($page, $perPage)->values();
        $paginator = new LengthAwarePaginator($items, $rows->count(), $perPage, $page);

        return [
            'data' => $paginator->items(),
            'meta' => ApiResponse::paginationMeta($paginator),
            'summary' => [
                'accounts' => $rows->count(),
                'debit' => $this->sumMoney($rows, 'debit'),
                'credit' => $this->sumMoney($rows, 'credit'),
            ],
        ];
    }

    private function profitLoss(Request $request, ?string $from, ?string $to, int $perPage): array
    {
        $totals = $this->reports->accountTypeTotals($request->user()?->tenant_id, $request->user()?->company_id, $from, $to, $request->user()?->store_id);
        $rows = collect(AccountCatalog::all())
            ->filter(fn (array $account) => in_array($account['group'], ['Income', 'Expenses'], true))
            ->map(function (array $account) use ($totals) {
                $entry = $totals->get($account['key']);
                $debit = MoneyAmount::decimal($entry?->debit_total ?? 0);
                $credit = MoneyAmount::decimal($entry?->credit_total ?? 0);
                $amountCents = $account['nature'] === 'credit'
                    ? MoneyAmount::cents($credit) - MoneyAmount::cents($debit)
                    : MoneyAmount::cents($debit) - MoneyAmount::cents($credit);

                return [
                    'code' => $account['code'],
                    'section' => $account['group'],
                    'account' => $account['name'],
                    'debit' => $debit,
                    'credit' => $credit,
                    'amount' => MoneyAmount::fromCents($amountCents),
                ];
            })
            ->values();

        $incomeTotal = $this->sumMoney($rows->where('section', 'Income'), 'amount');
        $expenseTotal = $this->sumMoney($rows->where('section', 'Expenses'), 'amount');
        $netProfitCents = MoneyAmount::cents($incomeTotal) - MoneyAmount::cents($expenseTotal);
        $rows->push([
            'code' => '',
            'section' => 'Result',
            'account' => $netProfitCents >= 0 ? 'Net Profit' : 'Net Loss',
            'debit' => '0.00',
            'credit' => '0.00',
            'amount' => MoneyAmount::fromCents($netProfitCents),
        ]);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = $rows->forPage($page, $perPage)->values();
        $paginator = new LengthAwarePaginator($items, $rows->count(), $perPage, $page);

        return [
            'data' => $paginator->items(),
            'meta' => ApiResponse::paginationMeta($paginator),
            'summary' => [
                'income' => $incomeTotal,
                'expense' => $expenseTotal,
                'net_profit' => MoneyAmount::fromCents($netProfitCents),
            ],
        ];
    }

    private function sumMoney(iterable $rows, string $key): string
    {
        $cents = collect($rows)->sum(fn (array $row): int => MoneyAmount::cents($row[$key] ?? 0));

        return MoneyAmount::fromCents($cents);
    }

    private function mrPerformance(Request $request): array
    {
        $payload = $this->mrPerformance->monthly($request->user(), $request->query());

        return [
            'data' => $payload['rows'],
            'meta' => [
                'current_page' => 1,
                'per_page' => count($payload['rows']) ?: 1,
                'total' => count($payload['rows']),
                'last_page' => 1,
            ],
            'summary' => $payload['totals'],
        ];
    }

    private function customerLedger(Request $request, int $customerId, ?string $from, ?string $to, int $perPage): array
    {
        if ($customerId < 1) {
            throw ValidationException::withMessages(['customer_id' => 'Customer is required for customer ledger.']);
        }

        return $this->paged($this->reports->customerLedgerQuery($request->user(), $customerId, $from, $to), $perPage);
    }

    private function productMovement(Request $request, int $productId, ?string $from, ?string $to, int $perPage): array
    {
        if ($productId < 1) {
            throw ValidationException::withMessages(['product_id' => 'Product is required for product movement.']);
        }

        return $this->paged($this->reports->productMovementQuery($request->user(), $productId, $from, $to), $perPage);
    }

    private function paged($query, int $perPage): array
    {
        return $this->reports->paginate($query, $perPage);
    }
}
