<?php

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Contracts\DumpingReportServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DumpingReportService implements DumpingReportServiceInterface
{
    public function slowMoving(Request $request, int $perPage): array
    {
        $from = $request->query('from', now()->subDays(90)->toDateString());
        $to = $request->query('to', now()->toDateString());

        $stock = DB::table('batches')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity_available) as stock_on_hand, MIN(expires_at) as nearest_expiry');

        $sales = DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->whereNull('sales_invoices.deleted_at')
            ->whereBetween('sales_invoices.invoice_date', [$from, $to])
            ->groupBy('sales_invoice_items.product_id')
            ->selectRaw('sales_invoice_items.product_id, SUM(sales_invoice_items.quantity) as sold_quantity, MAX(sales_invoices.invoice_date) as last_sale_date');

        $query = DB::table('products')
            ->leftJoinSub($stock, 'stock', 'stock.product_id', '=', 'products.id')
            ->leftJoinSub($sales, 'sales', 'sales.product_id', '=', 'products.id')
            ->leftJoin('divisions', 'divisions.id', '=', 'products.division_id')
            ->whereNull('products.deleted_at')
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('products.tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('products.company_id', $companyId))
            ->when($request->filled('division_id'), fn ($builder) => $builder->where('products.division_id', $request->integer('division_id')))
            ->when($request->filled('product_id'), fn ($builder) => $builder->where('products.id', $request->integer('product_id')))
            ->whereRaw('COALESCE(stock.stock_on_hand, 0) > 0')
            ->orderByRaw('COALESCE(sales.sold_quantity, 0) asc')
            ->orderByRaw('COALESCE(stock.stock_on_hand, 0) desc')
            ->selectRaw("
                products.id,
                products.product_code,
                products.name as product,
                products.group_name,
                products.hs_code,
                divisions.name as division,
                COALESCE(stock.stock_on_hand, 0) as stock_on_hand,
                COALESCE(sales.sold_quantity, 0) as sold_quantity,
                stock.nearest_expiry,
                sales.last_sale_date,
                products.purchase_price,
                (COALESCE(stock.stock_on_hand, 0) * products.purchase_price) as stock_value
            ");

        $page = $query->paginate($perPage);

        return [
            'data' => collect($page->items())->map(function ($row) {
                $stock = (float) $row->stock_on_hand;
                $sold = (float) $row->sold_quantity;
                $daysToExpiry = $row->nearest_expiry ? now()->diffInDays($row->nearest_expiry, false) : null;

                return [
                    ...get_object_vars($row),
                    'risk' => match (true) {
                        $daysToExpiry !== null && $daysToExpiry < 0 => 'expired',
                        $daysToExpiry !== null && $daysToExpiry <= 90 && $stock > $sold => 'near_expiry_unsold',
                        $sold <= 0 && $stock > 0 => 'no_movement',
                        $stock > max($sold * 4, 100) => 'overstock',
                        default => 'watch',
                    },
                    'days_to_expiry' => $daysToExpiry,
                ];
            })->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
            'summary' => [
                'stock_on_hand' => (float) collect($page->items())->sum('stock_on_hand'),
                'stock_value' => (float) collect($page->items())->sum('stock_value'),
            ],
        ];
    }
}
