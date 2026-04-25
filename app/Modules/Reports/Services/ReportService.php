<?php

namespace App\Modules\Reports\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public function run(string $report, Request $request): array
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $perPage = min((int) $request->query('per_page', 20), 100);

        return match ($report) {
            'sales' => $this->sales($from, $to, $perPage),
            'purchase' => $this->purchase($from, $to, $perPage),
            'stock' => $this->stock($perPage),
            'low-stock' => $this->lowStock($perPage),
            'expiry' => $this->expiry($request->query('to', now()->addMonths(3)->toDateString()), $perPage),
            'day-book' => $this->accountBook($from, $to, $perPage),
            'cash-book' => $this->accountBook($from, $to, $perPage, 'cash'),
            'bank-book' => $this->accountBook($from, $to, $perPage, 'bank'),
            'ledger' => $this->accountLedger($request->query('account_type'), $from, $to, $perPage),
            'supplier-performance' => $this->supplierPerformance($from, $to, $perPage),
            'supplier-ledger' => $this->supplierLedger((int) $request->query('supplier_id'), $from, $to, $perPage),
            'customer-ledger' => $this->customerLedger((int) $request->query('customer_id'), $from, $to, $perPage),
            'product-movement' => $this->productMovement((int) $request->query('product_id'), $from, $to, $perPage),
            default => throw ValidationException::withMessages(['report' => 'Unknown report.']),
        };
    }

    private function sales(string $from, string $to, int $perPage): array
    {
        $query = DB::table('sales_invoices')
            ->leftJoin('customers', 'customers.id', '=', 'sales_invoices.customer_id')
            ->whereNull('sales_invoices.deleted_at')
            ->whereBetween('sales_invoices.invoice_date', [$from, $to])
            ->orderByDesc('sales_invoices.invoice_date')
            ->selectRaw("sales_invoices.invoice_no, sales_invoices.invoice_date, COALESCE(customers.name, 'Walk-in') as customer, sales_invoices.payment_status, sales_invoices.grand_total, sales_invoices.paid_amount");

        return $this->paged($query, $perPage);
    }

    private function accountBook(string $from, string $to, int $perPage, ?string $accountType = null): array
    {
        $query = DB::table('account_transactions')
            ->whereBetween('transaction_date', [$from, $to])
            ->when($accountType, fn ($builder) => $builder->where('account_type', $accountType))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->selectRaw('transaction_date as date, account_type, party_type, party_id, source_type, source_id, debit, credit, notes');

        return $this->paged($query, $perPage);
    }

    private function accountLedger(?string $accountType, string $from, string $to, int $perPage): array
    {
        if (! $accountType) {
            throw ValidationException::withMessages(['account_type' => 'Account type is required for ledger.']);
        }

        return $this->accountBook($from, $to, $perPage, $accountType);
    }

    private function purchase(string $from, string $to, int $perPage): array
    {
        $query = DB::table('purchases')
            ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->whereNull('purchases.deleted_at')
            ->whereBetween('purchases.purchase_date', [$from, $to])
            ->orderByDesc('purchases.purchase_date')
            ->selectRaw('purchases.purchase_no, purchases.purchase_date, suppliers.name as supplier, purchases.payment_status, purchases.grand_total, purchases.paid_amount');

        return $this->paged($query, $perPage);
    }

    private function supplierLedger(int $supplierId, string $from, string $to, int $perPage): array
    {
        if ($supplierId < 1) {
            throw ValidationException::withMessages(['supplier_id' => 'Supplier is required for supplier ledger.']);
        }

        $query = DB::table('purchases')
            ->where('supplier_id', $supplierId)
            ->whereNull('deleted_at')
            ->whereBetween('purchase_date', [$from, $to])
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->selectRaw('purchase_date as date, purchase_no as reference, grand_total as credit, paid_amount as debit, payment_status');

        return $this->paged($query, $perPage);
    }

    private function stock(int $perPage): array
    {
        $query = DB::table('products')
            ->leftJoin('batches', function ($join) {
                $join->on('batches.product_id', '=', 'products.id')
                    ->whereNull('batches.deleted_at')
                    ->where('batches.is_active', true);
            })
            ->whereNull('products.deleted_at')
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.reorder_level')
            ->orderBy('products.name')
            ->selectRaw('products.id, products.name, products.sku, products.reorder_level, COALESCE(SUM(batches.quantity_available), 0) as stock_on_hand');

        return $this->paged($query, $perPage);
    }

    private function lowStock(int $perPage): array
    {
        $query = DB::table('products')
            ->leftJoin('batches', function ($join) {
                $join->on('batches.product_id', '=', 'products.id')
                    ->whereNull('batches.deleted_at')
                    ->where('batches.is_active', true);
            })
            ->whereNull('products.deleted_at')
            ->where('products.is_active', true)
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.reorder_level')
            ->havingRaw('COALESCE(SUM(batches.quantity_available), 0) <= products.reorder_level')
            ->orderBy('products.name')
            ->selectRaw('products.id, products.name, products.sku, products.reorder_level, COALESCE(SUM(batches.quantity_available), 0) as stock_on_hand');

        return $this->paged($query, $perPage);
    }

    private function expiry(string $to, int $perPage): array
    {
        $query = DB::table('batches')
            ->join('products', 'products.id', '=', 'batches.product_id')
            ->whereNull('batches.deleted_at')
            ->where('batches.quantity_available', '>', 0)
            ->whereDate('batches.expires_at', '<=', $to)
            ->orderBy('batches.expires_at')
            ->selectRaw('products.name as product, batches.batch_no, batches.expires_at, batches.quantity_available, batches.mrp');

        return $this->paged($query, $perPage);
    }

    private function supplierPerformance(string $from, string $to, int $perPage): array
    {
        $query = DB::table('suppliers')
            ->leftJoin('purchases', function ($join) use ($from, $to) {
                $join->on('purchases.supplier_id', '=', 'suppliers.id')
                    ->whereNull('purchases.deleted_at')
                    ->whereBetween('purchases.purchase_date', [$from, $to]);
            })
            ->whereNull('suppliers.deleted_at')
            ->groupBy('suppliers.id', 'suppliers.name', 'suppliers.current_balance')
            ->orderByDesc('purchase_total')
            ->selectRaw('suppliers.id, suppliers.name, suppliers.current_balance, COUNT(purchases.id) as purchase_count, COALESCE(SUM(purchases.grand_total), 0) as purchase_total');

        return $this->paged($query, $perPage);
    }

    private function customerLedger(int $customerId, string $from, string $to, int $perPage): array
    {
        if ($customerId < 1) {
            throw ValidationException::withMessages(['customer_id' => 'Customer is required for customer ledger.']);
        }

        $query = DB::table('sales_invoices')
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

        $query = DB::table('stock_movements')
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
