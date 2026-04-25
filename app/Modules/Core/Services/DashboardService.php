<?php

namespace App\Modules\Core\Services;

use App\Core\Support\WorkspaceScope;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function summary(array $filters = [], ?User $user = null): array
    {
        $today = CarbonImmutable::today();
        $from = CarbonImmutable::parse($filters['from'] ?? $today->startOfMonth()->toDateString());
        $to = CarbonImmutable::parse($filters['to'] ?? $today->endOfMonth()->toDateString());
        $representativeId = isset($filters['medical_representative_id']) ? (int) $filters['medical_representative_id'] : null;

        if ($user && $user->hasRole('MR') && (int) $user->medical_representative_id > 0) {
            return $this->representativeSummary($from, $to, $user->medical_representative_id, $user);
        }

        return [
            'period' => $from->format('j M Y').' - '.$to->format('j M Y'),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'medical_representative_id' => $representativeId,
            ],
            'stats' => [
                'today_sales' => $this->sumSales($today->toDateString(), $today->toDateString(), $user, $representativeId),
                'period_sales' => $this->sumSales($from->toDateString(), $to->toDateString(), $user, $representativeId),
                'period_purchase' => $this->sumPurchases($from->toDateString(), $to->toDateString(), $user),
                'low_stock' => $this->lowStockCount($user),
                'expiring_batches' => $this->expiringBatchCount($user),
                'receivables' => (float) WorkspaceScope::apply(DB::table('customers'), $user, 'customers', ['tenant_id', 'company_id'])->whereNull('deleted_at')->sum('current_balance'),
                'payables' => (float) WorkspaceScope::apply(DB::table('suppliers'), $user, 'suppliers', ['tenant_id', 'company_id'])->whereNull('deleted_at')->sum('current_balance'),
                'products' => WorkspaceScope::apply(DB::table('products'), $user, 'products', ['tenant_id', 'company_id'])->whereNull('deleted_at')->count(),
                'sales_invoices' => WorkspaceScope::apply(DB::table('sales_invoices'), $user, 'sales_invoices', ['tenant_id', 'company_id', 'store_id'])
                    ->whereNull('deleted_at')
                    ->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])
                    ->when($representativeId, fn ($query) => $query->where('medical_representative_id', $representativeId))
                    ->count(),
                'purchase_bills' => WorkspaceScope::apply(DB::table('purchases'), $user, 'purchases', ['tenant_id', 'company_id', 'store_id'])
                    ->whereNull('deleted_at')
                    ->whereBetween('purchase_date', [$from->toDateString(), $to->toDateString()])
                    ->count(),
            ],
            'top_products' => $this->topProducts($from->toDateString(), $to->toDateString(), $user, $representativeId),
            'low_stock_rows' => $this->lowStockRows($user),
            'expiry_rows' => $this->expiryRows($user),
            'top_representatives' => $this->topRepresentatives($from->toDateString(), $to->toDateString(), $user),
            'recent_sales' => WorkspaceScope::apply(DB::table('sales_invoices'), $user, 'sales_invoices', ['tenant_id', 'company_id', 'store_id'])
                ->leftJoin('customers', 'customers.id', '=', 'sales_invoices.customer_id')
                ->leftJoin('medical_representatives', 'medical_representatives.id', '=', 'sales_invoices.medical_representative_id')
                ->whereNull('sales_invoices.deleted_at')
                ->when($representativeId, fn ($query) => $query->where('sales_invoices.medical_representative_id', $representativeId))
                ->orderByDesc('sales_invoices.invoice_date')
                ->orderByDesc('sales_invoices.id')
                ->limit(6)
                ->get(['sales_invoices.id', 'sales_invoices.invoice_no', 'sales_invoices.invoice_date', 'sales_invoices.grand_total', 'sales_invoices.payment_status', 'customers.name as customer_name', 'medical_representatives.name as mr_name']),
            'recent_purchases' => WorkspaceScope::apply(DB::table('purchases'), $user, 'purchases', ['tenant_id', 'company_id', 'store_id'])
                ->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
                ->whereNull('purchases.deleted_at')
                ->orderByDesc('purchases.purchase_date')
                ->orderByDesc('purchases.id')
                ->limit(6)
                ->get(['purchases.id', 'purchases.purchase_no', 'purchases.purchase_date', 'purchases.grand_total', 'purchases.payment_status', 'suppliers.name as supplier_name']),
            'mr' => [
                'active' => WorkspaceScope::apply(DB::table('medical_representatives'), $user, 'medical_representatives', ['tenant_id', 'company_id'])->where('is_active', true)->whereNull('deleted_at')->count(),
                'month_orders' => (float) DB::table('representative_visits')
                    ->join('medical_representatives', 'medical_representatives.id', '=', 'representative_visits.medical_representative_id')
                    ->whereNull('representative_visits.deleted_at')
                    ->whereNull('medical_representatives.deleted_at')
                    ->when($user, fn ($query) => WorkspaceScope::apply($query, $user, 'medical_representatives', ['tenant_id', 'company_id']))
                    ->whereBetween('visit_date', [$from->toDateString(), $to->toDateString()])
                    ->sum('order_value'),
            ],
        ];
    }

    private function representativeSummary(CarbonImmutable $from, CarbonImmutable $to, int $representativeId, ?User $user = null): array
    {
        $visitQuery = DB::table('representative_visits')
            ->join('medical_representatives', 'medical_representatives.id', '=', 'representative_visits.medical_representative_id')
            ->whereNull('representative_visits.deleted_at')
            ->whereNull('medical_representatives.deleted_at')
            ->where('representative_visits.medical_representative_id', $representativeId)
            ->whereBetween('representative_visits.visit_date', [$from->toDateString(), $to->toDateString()]);

        if ($user) {
            WorkspaceScope::apply($visitQuery, $user, 'medical_representatives', ['tenant_id', 'company_id']);
        }

        $salesQuery = WorkspaceScope::apply(DB::table('sales_invoices'), $user, 'sales_invoices', ['tenant_id', 'company_id', 'store_id'])
            ->whereNull('deleted_at')
            ->where('medical_representative_id', $representativeId)
            ->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()]);

        $representative = WorkspaceScope::apply(DB::table('medical_representatives'), $user, 'medical_representatives', ['tenant_id', 'company_id'])
            ->whereKey($representativeId)
            ->first();

        return [
            'scope' => 'medical_representative',
            'period' => $from->format('j M Y').' - '.$to->format('j M Y'),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'medical_representative_id' => $representativeId,
            ],
            'stats' => [
                'today_sales' => round((float) WorkspaceScope::apply(DB::table('sales_invoices'), $user, 'sales_invoices', ['tenant_id', 'company_id', 'store_id'])
                    ->whereNull('deleted_at')
                    ->where('medical_representative_id', $representativeId)
                    ->whereDate('invoice_date', today()->toDateString())
                    ->sum('grand_total'), 2),
                'period_sales' => round((float) $salesQuery->sum('grand_total'), 2),
                'visits' => $visitQuery->count(),
                'visit_orders' => round((float) $visitQuery->sum('order_value'), 2),
                'invoices' => $salesQuery->count(),
                'target' => (float) ($representative->monthly_target ?? 0),
            ],
            'top_products' => $this->topProducts($from->toDateString(), $to->toDateString(), $user, $representativeId),
            'recent_sales' => WorkspaceScope::apply(DB::table('sales_invoices'), $user, 'sales_invoices', ['tenant_id', 'company_id', 'store_id'])
                ->leftJoin('customers', 'customers.id', '=', 'sales_invoices.customer_id')
                ->whereNull('sales_invoices.deleted_at')
                ->where('sales_invoices.medical_representative_id', $representativeId)
                ->orderByDesc('sales_invoices.invoice_date')
                ->orderByDesc('sales_invoices.id')
                ->limit(6)
                ->get(['sales_invoices.id', 'sales_invoices.invoice_no', 'sales_invoices.invoice_date', 'sales_invoices.grand_total', 'sales_invoices.payment_status', 'customers.name as customer_name']),
            'recent_visits' => DB::table('representative_visits')
                ->leftJoin('customers', 'customers.id', '=', 'representative_visits.customer_id')
                ->join('medical_representatives', 'medical_representatives.id', '=', 'representative_visits.medical_representative_id')
                ->whereNull('representative_visits.deleted_at')
                ->when($user, fn ($query) => WorkspaceScope::apply($query, $user, 'medical_representatives', ['tenant_id', 'company_id']))
                ->where('representative_visits.medical_representative_id', $representativeId)
                ->orderByDesc('representative_visits.visit_date')
                ->orderByDesc('representative_visits.id')
                ->limit(6)
                ->get(['representative_visits.id', 'representative_visits.visit_date', 'representative_visits.status', 'representative_visits.order_value', 'customers.name as customer_name']),
        ];
    }

    private function sumSales(string $from, string $to, ?User $user = null, ?int $representativeId = null): float
    {
        return round((float) WorkspaceScope::apply(DB::table('sales_invoices'), $user, 'sales_invoices', ['tenant_id', 'company_id', 'store_id'])
            ->whereNull('deleted_at')
            ->where('status', 'confirmed')
            ->whereBetween('invoice_date', [$from, $to])
            ->when($representativeId, fn ($query) => $query->where('medical_representative_id', $representativeId))
            ->sum('grand_total'), 2);
    }

    private function sumPurchases(string $from, string $to, ?User $user = null): float
    {
        return round((float) WorkspaceScope::apply(DB::table('purchases'), $user, 'purchases', ['tenant_id', 'company_id', 'store_id'])
            ->whereNull('deleted_at')
            ->whereBetween('purchase_date', [$from, $to])
            ->sum('grand_total'), 2);
    }

    private function lowStockCount(?User $user = null): int
    {
        return WorkspaceScope::apply(DB::table('products'), $user, 'products', ['tenant_id', 'company_id'])
            ->leftJoin('batches', function ($join) use ($user) {
                $join->on('batches.product_id', '=', 'products.id')
                    ->where('batches.is_active', true)
                    ->whereNull('batches.deleted_at');

                if ($user?->store_id) {
                    $join->where('batches.store_id', $user->store_id);
                }
            })
            ->whereNull('products.deleted_at')
            ->where('products.is_active', true)
            ->groupBy('products.id', 'products.reorder_level')
            ->havingRaw('COALESCE(SUM(batches.quantity_available), 0) <= products.reorder_level')
            ->get()
            ->count();
    }

    private function expiringBatchCount(?User $user = null): int
    {
        $today = CarbonImmutable::today();

        return WorkspaceScope::apply(DB::table('batches'), $user, 'batches', ['tenant_id', 'company_id', 'store_id'])
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('quantity_available', '>', 0)
            ->whereDate('expires_at', '>=', $today->toDateString())
            ->whereDate('expires_at', '<=', $today->addMonths(3)->toDateString())
            ->count();
    }

    private function topProducts(string $from, string $to, ?User $user = null, ?int $representativeId = null): array
    {
        return WorkspaceScope::apply(DB::table('sales_invoice_items'), $user, 'sales_invoices', ['tenant_id', 'company_id', 'store_id'])
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->join('products', 'products.id', '=', 'sales_invoice_items.product_id')
            ->whereNull('sales_invoices.deleted_at')
            ->whereBetween('sales_invoices.invoice_date', [$from, $to])
            ->when($representativeId, fn ($query) => $query->where('sales_invoices.medical_representative_id', $representativeId))
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

    private function lowStockRows(?User $user = null): array
    {
        return WorkspaceScope::apply(DB::table('products'), $user, 'products', ['tenant_id', 'company_id'])
            ->leftJoin('batches', function ($join) use ($user) {
                $join->on('batches.product_id', '=', 'products.id')
                    ->whereNull('batches.deleted_at')
                    ->where('batches.is_active', true);

                if ($user?->store_id) {
                    $join->where('batches.store_id', $user->store_id);
                }
            })
            ->whereNull('products.deleted_at')
            ->where('products.is_active', true)
            ->groupBy('products.id', 'products.name', 'products.reorder_level')
            ->havingRaw('COALESCE(SUM(batches.quantity_available), 0) <= products.reorder_level')
            ->orderByRaw('COALESCE(SUM(batches.quantity_available), 0) asc')
            ->limit(8)
            ->selectRaw('products.id, products.name, products.reorder_level, COALESCE(SUM(batches.quantity_available), 0) as stock_on_hand')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'stock_on_hand' => (float) $row->stock_on_hand,
                'reorder_level' => (float) $row->reorder_level,
            ])
            ->all();
    }

    private function expiryRows(?User $user = null): array
    {
        return WorkspaceScope::apply(DB::table('batches'), $user, 'batches', ['tenant_id', 'company_id', 'store_id'])
            ->join('products', 'products.id', '=', 'batches.product_id')
            ->whereNull('batches.deleted_at')
            ->where('batches.is_active', true)
            ->where('batches.quantity_available', '>', 0)
            ->whereDate('batches.expires_at', '>=', today()->toDateString())
            ->whereDate('batches.expires_at', '<=', today()->addMonths(3)->toDateString())
            ->orderBy('batches.expires_at')
            ->limit(8)
            ->get(['batches.id', 'products.name', 'batches.batch_no', 'batches.expires_at', 'batches.quantity_available'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'batch_no' => $row->batch_no,
                'expires_at' => $row->expires_at,
                'quantity_available' => (float) $row->quantity_available,
                'days_to_expiry' => CarbonImmutable::today()->diffInDays(CarbonImmutable::parse($row->expires_at), false),
            ])
            ->all();
    }

    private function topRepresentatives(string $from, string $to, ?User $user = null): array
    {
        return WorkspaceScope::apply(DB::table('medical_representatives'), $user, 'medical_representatives', ['tenant_id', 'company_id'])
            ->leftJoin('sales_invoices', function ($join) use ($from, $to, $user) {
                $join->on('sales_invoices.medical_representative_id', '=', 'medical_representatives.id')
                    ->whereNull('sales_invoices.deleted_at')
                    ->whereBetween('sales_invoices.invoice_date', [$from, $to]);

                if ($user?->store_id) {
                    $join->where('sales_invoices.store_id', $user->store_id);
                }
            })
            ->whereNull('medical_representatives.deleted_at')
            ->groupBy('medical_representatives.id', 'medical_representatives.name', 'medical_representatives.territory')
            ->orderByDesc(DB::raw('COALESCE(SUM(sales_invoices.grand_total), 0)'))
            ->limit(6)
            ->selectRaw('medical_representatives.id, medical_representatives.name, medical_representatives.territory, COUNT(sales_invoices.id) as invoices, COALESCE(SUM(sales_invoices.grand_total), 0) as amount')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'territory' => $row->territory,
                'invoices' => (int) $row->invoices,
                'amount' => (float) $row->amount,
            ])
            ->all();
    }
}
