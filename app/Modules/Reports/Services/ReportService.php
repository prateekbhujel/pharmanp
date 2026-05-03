<?php

namespace App\Modules\Reports\Services;

use App\Core\Support\ApiResponse;
use App\Modules\Accounting\Support\AccountCatalog;
use App\Modules\Analytics\Services\PharmaSignalService;
use App\Modules\MR\Services\MrPerformanceService;
use App\Modules\Reports\DTOs\ReportQueryData;
use App\Modules\Reports\Repositories\Interfaces\ReportRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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

    public function run(string $report, Request $request): array
    {
        $query = ReportQueryData::fromRequest($request);

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
        $query = DB::table('sales_invoices')
            ->leftJoin('customers', 'customers.id', '=', 'sales_invoices.customer_id')
            ->leftJoin('medical_representatives', 'medical_representatives.id', '=', 'sales_invoices.medical_representative_id')
            ->whereNull('sales_invoices.deleted_at')
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('sales_invoices.tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('sales_invoices.company_id', $companyId))
            ->when($request->filled('customer_id'), fn ($builder) => $builder->where('sales_invoices.customer_id', $request->integer('customer_id')))
            ->when($request->filled('payment_status'), fn ($builder) => $builder->where('sales_invoices.payment_status', $request->query('payment_status')))
            ->when($request->filled('medical_representative_id'), fn ($builder) => $builder->where('sales_invoices.medical_representative_id', $request->integer('medical_representative_id')))
            ->orderByDesc('sales_invoices.invoice_date')
            ->selectRaw("sales_invoices.id, sales_invoices.invoice_no, sales_invoices.invoice_date, COALESCE(customers.name, 'Walk-in') as customer, COALESCE(medical_representatives.name, '-') as mr_name, sales_invoices.payment_status, sales_invoices.grand_total, sales_invoices.paid_amount");

        $this->applyDateRange($query, 'sales_invoices.invoice_date', $from, $to);

        return $this->paged($query, $perPage);
    }

    private function accountBook(Request $request, ?string $from, ?string $to, int $perPage, ?string $accountType = null): array
    {
        $query = DB::table('account_transactions')
            ->leftJoin('customers', function ($join) {
                $join->on('customers.id', '=', 'account_transactions.party_id')
                    ->where('account_transactions.party_type', '=', 'customer');
            })
            ->leftJoin('suppliers', function ($join) {
                $join->on('suppliers.id', '=', 'account_transactions.party_id')
                    ->where('account_transactions.party_type', '=', 'supplier');
            })
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('account_transactions.tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('account_transactions.company_id', $companyId))
            ->when($accountType, fn ($builder) => $builder->where('account_type', $accountType))
            ->when($request->filled('party_type'), fn ($builder) => $builder->where('party_type', $request->query('party_type')))
            ->when($request->filled('party_id'), fn ($builder) => $builder->where('party_id', $request->integer('party_id')))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->selectRaw("transaction_date as date, account_type, party_type, party_id, COALESCE(customers.name, suppliers.name, '-') as party_name, source_type, source_id, debit, credit, notes");

        $this->applyDateRange($query, 'transaction_date', $from, $to);

        $page = $this->paged($query, $perPage);
        $labels = AccountCatalog::labels();

        $page['data'] = collect($page['data'])->map(function ($row) use ($labels) {
            $row->account_label = $labels[$row->account_type] ?? $row->account_type;

            return $row;
        })->all();

        $totals = $this->reports->accountTransactionTotals($request->user()?->tenant_id, $request->user()?->company_id, $from, $to, $accountType);
        $page['summary'] = [
            'debit' => (float) $totals->debit_total,
            'credit' => (float) $totals->credit_total,
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
        $query = DB::table('purchases')
            ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->whereNull('purchases.deleted_at')
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('purchases.tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('purchases.company_id', $companyId))
            ->when($request->filled('supplier_id'), fn ($builder) => $builder->where('purchases.supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('payment_status'), fn ($builder) => $builder->where('purchases.payment_status', $request->query('payment_status')))
            ->orderByDesc('purchases.purchase_date')
            ->selectRaw('purchases.id, purchases.purchase_no, purchases.purchase_date, purchases.supplier_invoice_no, suppliers.name as supplier, purchases.payment_status, purchases.grand_total, purchases.paid_amount');

        $this->applyDateRange($query, 'purchases.purchase_date', $from, $to);

        return $this->paged($query, $perPage);
    }

    private function supplierLedger(Request $request, int $supplierId, ?string $from, ?string $to, int $perPage): array
    {
        if ($supplierId < 1) {
            throw ValidationException::withMessages(['supplier_id' => 'Supplier is required for supplier ledger.']);
        }

        $query = DB::table('purchases')
            ->where('supplier_id', $supplierId)
            ->whereNull('deleted_at')
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('company_id', $companyId))
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->selectRaw('purchase_date as date, purchase_no as reference, grand_total as credit, paid_amount as debit, payment_status');

        $this->applyDateRange($query, 'purchase_date', $from, $to);

        return $this->paged($query, $perPage);
    }

    private function stock(Request $request, int $perPage): array
    {
        $query = DB::table('products')
            ->leftJoin('batches', function ($join) {
                $join->on('batches.product_id', '=', 'products.id')
                    ->whereNull('batches.deleted_at')
                    ->where('batches.is_active', true);
            })
            ->whereNull('products.deleted_at')
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('products.tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('products.company_id', $companyId))
            ->when($request->filled('company_id'), fn ($builder) => $builder->where('products.company_id', $request->integer('company_id')))
            ->when($request->filled('category_id'), fn ($builder) => $builder->where('products.category_id', $request->integer('category_id')))
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.reorder_level')
            ->orderBy('products.name')
            ->selectRaw('products.id, products.name, products.sku, products.reorder_level, COALESCE(SUM(batches.quantity_available), 0) as stock_on_hand');

        return $this->paged($query, $perPage);
    }

    private function lowStock(Request $request, int $perPage): array
    {
        $query = DB::table('products')
            ->leftJoin('batches', function ($join) {
                $join->on('batches.product_id', '=', 'products.id')
                    ->whereNull('batches.deleted_at')
                    ->where('batches.is_active', true);
            })
            ->whereNull('products.deleted_at')
            ->where('products.is_active', true)
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('products.tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('products.company_id', $companyId))
            ->when($request->filled('company_id'), fn ($builder) => $builder->where('products.company_id', $request->integer('company_id')))
            ->when($request->filled('category_id'), fn ($builder) => $builder->where('products.category_id', $request->integer('category_id')))
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.reorder_level')
            ->havingRaw('COALESCE(SUM(batches.quantity_available), 0) <= products.reorder_level')
            ->orderBy('products.name')
            ->selectRaw('products.id, products.name, products.sku, products.reorder_level, COALESCE(SUM(batches.quantity_available), 0) as stock_on_hand');

        return $this->paged($query, $perPage);
    }

    private function expiry(Request $request, ?string $from, ?string $to, int $perPage): array
    {
        $query = DB::table('batches')
            ->join('products', 'products.id', '=', 'batches.product_id')
            ->whereNull('batches.deleted_at')
            ->where('batches.quantity_available', '>', 0)
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('batches.tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('batches.company_id', $companyId))
            ->when($request->filled('product_id'), fn ($builder) => $builder->where('batches.product_id', $request->integer('product_id')))
            ->orderBy('batches.expires_at')
            ->selectRaw('products.id as product_id, products.name as product, batches.batch_no, batches.expires_at, batches.quantity_available, batches.mrp');

        $this->applyDateRange($query, 'batches.expires_at', $from, $to);

        return $this->paged($query, $perPage);
    }

    private function supplierPerformance(Request $request, ?string $from, ?string $to, int $perPage): array
    {
        $query = DB::table('suppliers')
            ->leftJoin('purchases', function ($join) use ($from, $to) {
                $join->on('purchases.supplier_id', '=', 'suppliers.id')
                    ->whereNull('purchases.deleted_at')
                    ->when($from, fn ($builder) => $builder->where('purchases.purchase_date', '>=', $from))
                    ->when($to, fn ($builder) => $builder->where('purchases.purchase_date', '<=', $to));
            })
            ->whereNull('suppliers.deleted_at')
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('suppliers.tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('suppliers.company_id', $companyId))
            ->groupBy('suppliers.id', 'suppliers.name', 'suppliers.current_balance')
            ->orderByDesc('purchase_total')
            ->selectRaw('suppliers.id, suppliers.name, suppliers.current_balance, COUNT(purchases.id) as purchase_count, COALESCE(SUM(purchases.grand_total), 0) as purchase_total');

        return $this->paged($query, $perPage);
    }

    private function trialBalance(Request $request, ?string $from, ?string $to, int $perPage): array
    {
        $summary = $this->reports->accountTypeTotals($request->user()?->tenant_id, $request->user()?->company_id, $from, $to);

        $rows = collect(AccountCatalog::all())
            ->map(function (array $account) use ($summary) {
                $totals = $summary->get($account['key']);
                $debit = round((float) ($totals?->debit_total ?? 0), 2);
                $credit = round((float) ($totals?->credit_total ?? 0), 2);
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
            ->filter(fn (array $row) => $row['debit'] > 0 || $row['credit'] > 0)
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = $rows->forPage($page, $perPage)->values();
        $paginator = new LengthAwarePaginator($items, $rows->count(), $perPage, $page);

        return [
            'data' => $paginator->items(),
            'meta' => ApiResponse::paginationMeta($paginator),
            'summary' => [
                'debit' => round((float) $rows->sum('debit'), 2),
                'credit' => round((float) $rows->sum('credit'), 2),
                'difference' => round(abs((float) $rows->sum('debit') - (float) $rows->sum('credit')), 2),
            ],
        ];
    }

    private function accountTree(Request $request, ?string $from, ?string $to, int $perPage): array
    {
        $summary = $this->reports->accountTypeTotals($request->user()?->tenant_id, $request->user()?->company_id, $from, $to);
        $rows = collect(AccountCatalog::all())
            ->map(function (array $account) use ($summary) {
                $totals = $summary->get($account['key']);
                $debit = round((float) ($totals?->debit_total ?? 0), 2);
                $credit = round((float) ($totals?->credit_total ?? 0), 2);
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
                'debit' => round((float) $rows->sum('debit'), 2),
                'credit' => round((float) $rows->sum('credit'), 2),
            ],
        ];
    }

    private function profitLoss(Request $request, ?string $from, ?string $to, int $perPage): array
    {
        $totals = $this->reports->accountTypeTotals($request->user()?->tenant_id, $request->user()?->company_id, $from, $to);
        $rows = collect(AccountCatalog::all())
            ->filter(fn (array $account) => in_array($account['group'], ['Income', 'Expenses'], true))
            ->map(function (array $account) use ($totals) {
                $entry = $totals->get($account['key']);
                $debit = (float) ($entry?->debit_total ?? 0);
                $credit = (float) ($entry?->credit_total ?? 0);
                $amount = $account['nature'] === 'credit'
                    ? $credit - $debit
                    : $debit - $credit;

                return [
                    'code' => $account['code'],
                    'section' => $account['group'],
                    'account' => $account['name'],
                    'debit' => $debit,
                    'credit' => $credit,
                    'amount' => $amount,
                ];
            })
            ->values();

        $incomeTotal = (float) $rows->where('section', 'Income')->sum('amount');
        $expenseTotal = (float) $rows->where('section', 'Expenses')->sum('amount');
        $netProfit = $incomeTotal - $expenseTotal;
        $rows->push([
            'code' => '',
            'section' => 'Result',
            'account' => $netProfit >= 0 ? 'Net Profit' : 'Net Loss',
            'debit' => 0,
            'credit' => 0,
            'amount' => $netProfit,
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
                'net_profit' => $netProfit,
            ],
        ];
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

        $query = DB::table('sales_invoices')
            ->where('customer_id', $customerId)
            ->whereNull('deleted_at')
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('company_id', $companyId))
            ->orderBy('invoice_date')
            ->selectRaw('invoice_date as date, invoice_no as reference, grand_total as debit, paid_amount as credit, payment_status');

        $this->applyDateRange($query, 'invoice_date', $from, $to);

        return $this->paged($query, $perPage);
    }

    private function productMovement(Request $request, int $productId, ?string $from, ?string $to, int $perPage): array
    {
        if ($productId < 1) {
            throw ValidationException::withMessages(['product_id' => 'Product is required for product movement.']);
        }

        $query = DB::table('stock_movements')
            ->leftJoin('batches', 'batches.id', '=', 'stock_movements.batch_id')
            ->where('stock_movements.product_id', $productId)
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('stock_movements.tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('stock_movements.company_id', $companyId))
            ->orderBy('stock_movements.movement_date')
            ->orderBy('stock_movements.id')
            ->selectRaw('stock_movements.movement_date, stock_movements.movement_type, batches.batch_no, stock_movements.quantity_in, stock_movements.quantity_out, stock_movements.notes');

        $this->applyDateRange($query, 'stock_movements.movement_date', $from, $to);

        return $this->paged($query, $perPage);
    }

    private function applyDateRange($query, string $column, ?string $from, ?string $to): void
    {
        if ($from) {
            $query->whereDate($column, '>=', $from);
        }

        if ($to) {
            $query->whereDate($column, '<=', $to);
        }
    }

    private function paged($query, int $perPage): array
    {
        return $this->reports->paginate($query, $perPage);
    }
}
