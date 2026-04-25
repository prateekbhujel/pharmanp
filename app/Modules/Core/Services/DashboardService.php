<?php

namespace App\Modules\Core\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function summary(): array
    {
        $today = CarbonImmutable::today();
        $monthStart = $today->startOfMonth();
        $monthEnd = $today->endOfMonth();

        return [
            'period' => $today->format('F Y'),
            'stats' => [
                'today_sales' => $this->sumSales($today->toDateString(), $today->toDateString()),
                'month_sales' => $this->sumSales($monthStart->toDateString(), $monthEnd->toDateString()),
                'month_purchase' => $this->sumPurchases($monthStart->toDateString(), $monthEnd->toDateString()),
                'low_stock' => $this->lowStockCount(),
                'expiring_batches' => $this->expiringBatchCount(),
                'receivables' => (float) DB::table('customers')->whereNull('deleted_at')->sum('current_balance'),
                'payables' => (float) DB::table('suppliers')->whereNull('deleted_at')->sum('current_balance'),
                'products' => DB::table('products')->whereNull('deleted_at')->count(),
            ],
            'top_products' => $this->topProducts($monthStart->toDateString(), $monthEnd->toDateString()),
            'recent_sales' => DB::table('sales_invoices')
                ->leftJoin('customers', 'customers.id', '=', 'sales_invoices.customer_id')
                ->whereNull('sales_invoices.deleted_at')
                ->orderByDesc('sales_invoices.invoice_date')
                ->orderByDesc('sales_invoices.id')
                ->limit(6)
                ->get(['sales_invoices.id', 'sales_invoices.invoice_no', 'sales_invoices.invoice_date', 'sales_invoices.grand_total', 'customers.name as customer_name']),
            'recent_purchases' => DB::table('purchases')
                ->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
                ->whereNull('purchases.deleted_at')
                ->orderByDesc('purchases.purchase_date')
                ->orderByDesc('purchases.id')
                ->limit(6)
                ->get(['purchases.id', 'purchases.purchase_no', 'purchases.purchase_date', 'purchases.grand_total', 'suppliers.name as supplier_name']),
            'mr' => [
                'active' => DB::table('medical_representatives')->where('is_active', true)->whereNull('deleted_at')->count(),
                'month_orders' => (float) DB::table('representative_visits')
                    ->whereNull('deleted_at')
                    ->whereBetween('visit_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->sum('order_value'),
            ],
        ];
    }

    private function sumSales(string $from, string $to): float
    {
        return round((float) DB::table('sales_invoices')
            ->whereNull('deleted_at')
            ->where('status', 'confirmed')
            ->whereBetween('invoice_date', [$from, $to])
            ->sum('grand_total'), 2);
    }

    private function sumPurchases(string $from, string $to): float
    {
        return round((float) DB::table('purchases')
            ->whereNull('deleted_at')
            ->whereBetween('purchase_date', [$from, $to])
            ->sum('grand_total'), 2);
    }

    private function lowStockCount(): int
    {
        return DB::table('products')
            ->leftJoin('batches', function ($join) {
                $join->on('batches.product_id', '=', 'products.id')
                    ->where('batches.is_active', true)
                    ->whereNull('batches.deleted_at');
            })
            ->whereNull('products.deleted_at')
            ->where('products.is_active', true)
            ->groupBy('products.id', 'products.reorder_level')
            ->havingRaw('COALESCE(SUM(batches.quantity_available), 0) <= products.reorder_level')
            ->get()
            ->count();
    }

    private function expiringBatchCount(): int
    {
        $today = CarbonImmutable::today();

        return DB::table('batches')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('quantity_available', '>', 0)
            ->whereDate('expires_at', '>=', $today->toDateString())
            ->whereDate('expires_at', '<=', $today->addMonths(3)->toDateString())
            ->count();
    }

    private function topProducts(string $from, string $to): array
    {
        return DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->join('products', 'products.id', '=', 'sales_invoice_items.product_id')
            ->whereNull('sales_invoices.deleted_at')
            ->whereBetween('sales_invoices.invoice_date', [$from, $to])
            ->groupBy('products.id', 'products.name')
            ->selectRaw('products.id, products.name, SUM(sales_invoice_items.quantity) as quantity, SUM(sales_invoice_items.line_total) as amount')
            ->orderByDesc('quantity')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'quantity' => (float) $row->quantity,
                'amount' => (float) $row->amount,
            ])
            ->all();
    }
}
