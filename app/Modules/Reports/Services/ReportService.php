<?php

namespace App\Modules\Reports\Services;

use App\Core\Support\WorkspaceScope;
use App\Modules\Accounting\Support\AccountCatalog;
use App\Modules\MR\Services\MrPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public function __construct(
        private readonly MrPerformanceService $mrPerformance,
    ) {}

    public function run(string $report, Request $request): array
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $perPage = min((int) $request->query('per_page', 20), 100);

        return match ($report) {
            'sales' => $this->sales($request, $from, $to, $perPage),
            'purchase' => $this->purchase($request, $from, $to, $perPage),
            'stock' => $this->stock($request, $perPage),
            'low-stock' => $this->lowStock($request, $perPage),
            'expiry' => $this->expiry($request, $request->query('to', now()->addMonths(3)->toDateString()), $perPage),
            'day-book' => $this->accountBook($request, $from, $to, $perPage),
            'cash-book' => $this->accountBook($request, $from, $to, $perPage, 'cash'),
            'bank-book' => $this->accountBook($request, $from, $to, $perPage, 'bank'),
            'ledger' => $this->accountLedger($request, $request->query('account_type'), $from, $to, $perPage),
            'trial-balance' => $this->trialBalance($request, $from, $to, $perPage),
            'supplier-performance' => $this->supplierPerformance($request, $from, $to, $perPage),
            'supplier-ledger' => $this->supplierLedger((int) $request->query('supplier_id'), $from, $to, $perPage),
            'customer-ledger' => $this->customerLedger((int) $request->query('customer_id'), $from, $to, $perPage),
            'product-movement' => $this->productMovement((int) $request->query('product_id'), $from, $to, $perPage),
            'mr-performance' => $this->mrPerformance($request),
            default => throw ValidationException::withMessages(['report' => 'Unknown report.']),
        };
    }

    private function sales(Request $request, string $from, string $to, int $perPage): array
    {
        $query = WorkspaceScope::apply(DB::table('sales_invoices'), $request->user(), 'sales_invoices', ['tenant_id', 'company_id', 'store_id'])
            ->leftJoin('customers', 'customers.id', '=', 'sales_invoices.customer_id')
            ->leftJoin('medical_representatives', 'medical_representatives.id', '=', 'sales_invoices.medical_representative_id')
            ->whereNull('sales_invoices.deleted_at')
            ->whereBetween('sales_invoices.invoice_date', [$from, $to])
            ->when($request->filled('customer_id'), fn ($builder) => $builder->where('sales_invoices.customer_id', $request->integer('customer_id')))
            ->when($request->filled('payment_status'), fn ($builder) => $builder->where('sales_invoices.payment_status', $request->query('payment_status')))
            ->when($request->filled('medical_representative_id'), fn ($builder) => $builder->where('sales_invoices.medical_representative_id', $request->integer('medical_representative_id')))
            ->orderByDesc('sales_invoices.invoice_date')
            ->selectRaw("sales_invoices.id, sales_invoices.invoice_no, sales_invoices.invoice_date, COALESCE(customers.name, 'Walk-in') as customer, COALESCE(medical_representatives.name, '-') as mr_name, sales_invoices.payment_status, sales_invoices.grand_total, sales_invoices.paid_amount");

        return $this->paged($query, $perPage);
    }

    private function accountBook(Request $request, string $from, string $to, int $perPage, ?string $accountType = null): array
    {
        $query = WorkspaceScope::apply(DB::table('account_transactions'), $request->user(), 'account_transactions', ['tenant_id', 'company_id'])
            ->leftJoin('customers', function ($join) {
                $join->on('customers.id', '=', 'account_transactions.party_id')
                    ->where('account_transactions.party_type', '=', 'customer');
            })
            ->leftJoin('suppliers', function ($join) {
                $join->on('suppliers.id', '=', 'account_transactions.party_id')
                    ->where('account_transactions.party_type', '=', 'supplier');
            })
            ->whereBetween('transaction_date', [$from, $to])
            ->when($accountType, fn ($builder) => $builder->where('account_type', $accountType))
            ->when($request->filled('party_type'), fn ($builder) => $builder->where('party_type', $request->query('party_type')))
            ->when($request->filled('party_id'), fn ($builder) => $builder->where('party_id', $request->integer('party_id')))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->selectRaw("transaction_date as date, account_type, party_type, party_id, COALESCE(customers.name, suppliers.name, '-') as party_name, source_type, source_id, debit, credit, notes");

        $page = $this->paged($query, $perPage);
        $labels = AccountCatalog::labels();

        $page['data'] = collect($page['data'])->map(function ($row) use ($labels) {
            $row->account_label = $labels[$row->account_type] ?? $row->account_type;

            return $row;
        })->all();

        $page['summary'] = [
            'debit' => round((float) WorkspaceScope::apply(DB::table('account_transactions'), $request->user(), 'account_transactions', ['tenant_id', 'company_id'])
                ->whereBetween('transaction_date', [$from, $to])
                ->when($accountType, fn ($builder) => $builder->where('account_type', $accountType))
                ->sum('debit'), 2),
            'credit' => round((float) WorkspaceScope::apply(DB::table('account_transactions'), $request->user(), 'account_transactions', ['tenant_id', 'company_id'])
                ->whereBetween('transaction_date', [$from, $to])
                ->when($accountType, fn ($builder) => $builder->where('account_type', $accountType))
                ->sum('credit'), 2),
        ];

        return $page;
    }

    private function accountLedger(Request $request, ?string $accountType, string $from, string $to, int $perPage): array
    {
        if (! $accountType) {
            throw ValidationException::withMessages(['account_type' => 'Account type is required for ledger.']);
        }

        return $this->accountBook($request, $from, $to, $perPage, $accountType);
    }

    private function purchase(Request $request, string $from, string $to, int $perPage): array
    {
        $query = WorkspaceScope::apply(DB::table('purchases'), $request->user(), 'purchases', ['tenant_id', 'company_id', 'store_id'])
            ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->whereNull('purchases.deleted_at')
            ->whereBetween('purchases.purchase_date', [$from, $to])
            ->when($request->filled('supplier_id'), fn ($builder) => $builder->where('purchases.supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('payment_status'), fn ($builder) => $builder->where('purchases.payment_status', $request->query('payment_status')))
            ->orderByDesc('purchases.purchase_date')
            ->selectRaw('purchases.id, purchases.purchase_no, purchases.purchase_date, purchases.supplier_invoice_no, suppliers.name as supplier, purchases.payment_status, purchases.grand_total, purchases.paid_amount');

        return $this->paged($query, $perPage);
    }

    private function supplierLedger(int $supplierId, string $from, string $to, int $perPage): array
    {
        if ($supplierId < 1) {
            throw ValidationException::withMessages(['supplier_id' => 'Supplier is required for supplier ledger.']);
        }

        $query = WorkspaceScope::apply(DB::table('purchases'), request()->user(), 'purchases', ['tenant_id', 'company_id', 'store_id'])
            ->where('supplier_id', $supplierId)
            ->whereNull('deleted_at')
            ->whereBetween('purchase_date', [$from, $to])
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->selectRaw('purchase_date as date, purchase_no as reference, grand_total as credit, paid_amount as debit, payment_status');

        return $this->paged($query, $perPage);
    }

    private function stock(Request $request, int $perPage): array
    {
        $query = WorkspaceScope::apply(DB::table('products'), $request->user(), 'products', ['tenant_id', 'company_id'])
            ->leftJoin('batches', function ($join) use ($request) {
                $join->on('batches.product_id', '=', 'products.id')
                    ->whereNull('batches.deleted_at')
                    ->where('batches.is_active', true);

                if ($request->user()?->store_id) {
                    $join->where('batches.store_id', $request->user()->store_id);
                }
            })
            ->whereNull('products.deleted_at')
            ->when($request->filled('company_id'), fn ($builder) => $builder->where('products.company_id', $request->integer('company_id')))
            ->when($request->filled('category_id'), fn ($builder) => $builder->where('products.category_id', $request->integer('category_id')))
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.reorder_level')
            ->orderBy('products.name')
            ->selectRaw('products.id, products.name, products.sku, products.reorder_level, COALESCE(SUM(batches.quantity_available), 0) as stock_on_hand');

        return $this->paged($query, $perPage);
    }

    private function lowStock(Request $request, int $perPage): array
    {
        $query = WorkspaceScope::apply(DB::table('products'), $request->user(), 'products', ['tenant_id', 'company_id'])
            ->leftJoin('batches', function ($join) use ($request) {
                $join->on('batches.product_id', '=', 'products.id')
                    ->whereNull('batches.deleted_at')
                    ->where('batches.is_active', true);

                if ($request->user()?->store_id) {
                    $join->where('batches.store_id', $request->user()->store_id);
                }
            })
            ->whereNull('products.deleted_at')
            ->where('products.is_active', true)
            ->when($request->filled('company_id'), fn ($builder) => $builder->where('products.company_id', $request->integer('company_id')))
            ->when($request->filled('category_id'), fn ($builder) => $builder->where('products.category_id', $request->integer('category_id')))
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.reorder_level')
            ->havingRaw('COALESCE(SUM(batches.quantity_available), 0) <= products.reorder_level')
            ->orderBy('products.name')
            ->selectRaw('products.id, products.name, products.sku, products.reorder_level, COALESCE(SUM(batches.quantity_available), 0) as stock_on_hand');

        return $this->paged($query, $perPage);
    }

    private function expiry(Request $request, string $to, int $perPage): array
    {
        $query = WorkspaceScope::apply(DB::table('batches'), $request->user(), 'batches', ['tenant_id', 'company_id', 'store_id'])
            ->join('products', 'products.id', '=', 'batches.product_id')
            ->whereNull('batches.deleted_at')
            ->where('batches.quantity_available', '>', 0)
            ->when($request->filled('product_id'), fn ($builder) => $builder->where('batches.product_id', $request->integer('product_id')))
            ->whereDate('batches.expires_at', '<=', $to)
            ->orderBy('batches.expires_at')
            ->selectRaw('products.id as product_id, products.name as product, batches.batch_no, batches.expires_at, batches.quantity_available, batches.mrp');

        return $this->paged($query, $perPage);
    }

    private function supplierPerformance(Request $request, string $from, string $to, int $perPage): array
    {
        $query = WorkspaceScope::apply(DB::table('suppliers'), $request->user(), 'suppliers', ['tenant_id', 'company_id'])
            ->leftJoin('purchases', function ($join) use ($from, $to, $request) {
                $join->on('purchases.supplier_id', '=', 'suppliers.id')
                    ->whereNull('purchases.deleted_at')
                    ->whereBetween('purchases.purchase_date', [$from, $to]);

                if ($request->user()?->store_id) {
                    $join->where('purchases.store_id', $request->user()->store_id);
                }
            })
            ->whereNull('suppliers.deleted_at')
            ->groupBy('suppliers.id', 'suppliers.name', 'suppliers.current_balance')
            ->orderByDesc('purchase_total')
            ->selectRaw('suppliers.id, suppliers.name, suppliers.current_balance, COUNT(purchases.id) as purchase_count, COALESCE(SUM(purchases.grand_total), 0) as purchase_total');

        return $this->paged($query, $perPage);
    }

    private function trialBalance(Request $request, string $from, string $to, int $perPage): array
    {
        $summary = WorkspaceScope::apply(DB::table('account_transactions'), $request->user(), 'account_transactions', ['tenant_id', 'company_id'])
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw('account_type, SUM(debit) as debit_total, SUM(credit) as credit_total')
            ->groupBy('account_type')
            ->get()
            ->keyBy('account_type');

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
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'summary' => [
                'accounts' => $rows->count(),
                'debit' => round((float) $rows->sum('debit'), 2),
                'credit' => round((float) $rows->sum('credit'), 2),
                'closing_debit' => round((float) $rows->where('closing_side', 'Dr')->sum('closing_amount'), 2),
                'closing_credit' => round((float) $rows->where('closing_side', 'Cr')->sum('closing_amount'), 2),
                'difference' => round(abs((float) $rows->sum('debit') - (float) $rows->sum('credit')), 2),
                'balanced' => round(abs((float) $rows->sum('debit') - (float) $rows->sum('credit')), 2) === 0.0,
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

    private function customerLedger(int $customerId, string $from, string $to, int $perPage): array
    {
        if ($customerId < 1) {
            throw ValidationException::withMessages(['customer_id' => 'Customer is required for customer ledger.']);
        }

        $query = WorkspaceScope::apply(DB::table('sales_invoices'), request()->user(), 'sales_invoices', ['tenant_id', 'company_id', 'store_id'])
            ->where('customer_id', $customerId)
            ->whereNull('deleted_at')
            ->whereBetween('invoice_date', [$from, $to])
            ->orderBy('invoice_date')
            ->selectRaw('invoice_date as date, invoice_no as reference, grand_total as debit, paid_amount as credit, payment_status');

        return $this->paged($query, $perPage);
    }

    private function productMovement(int $productId, string $from, string $to, int $perPage): array
    {
        if ($productId < 1) {
            throw ValidationException::withMessages(['product_id' => 'Product is required for product movement.']);
        }

        $query = WorkspaceScope::apply(DB::table('stock_movements'), request()->user(), 'stock_movements', ['tenant_id', 'company_id', 'store_id'])
            ->leftJoin('batches', 'batches.id', '=', 'stock_movements.batch_id')
            ->where('stock_movements.product_id', $productId)
            ->whereBetween('stock_movements.movement_date', [$from, $to])
            ->orderBy('stock_movements.movement_date')
            ->orderBy('stock_movements.id')
            ->selectRaw('stock_movements.movement_date, stock_movements.movement_type, batches.batch_no, stock_movements.quantity_in, stock_movements.quantity_out, stock_movements.notes');

        return $this->paged($query, $perPage);
    }

    private function paged($query, int $perPage): array
    {
        $page = $query->paginate($perPage);

        return [
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ];
    }
}
