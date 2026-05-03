<?php

namespace App\Modules\Analytics\Repositories;

use App\Modules\Analytics\DTOs\InventorySignalFilterData;
use App\Modules\Analytics\Repositories\Interfaces\InventorySignalRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventorySignalRepository implements InventorySignalRepositoryInterface
{
    public function rows(InventorySignalFilterData $filters, CarbonImmutable $today): Collection
    {
        $from90 = $today->subDays(90)->toDateString();
        $from30 = $today->subDays(30)->toDateString();
        $expiryTo = $today->addDays(90)->toDateString();

        $sales90 = DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->whereNull('sales_invoices.deleted_at')
            ->whereDate('sales_invoices.invoice_date', '>=', $from90)
            ->groupBy('sales_invoice_items.product_id')
            ->selectRaw('sales_invoice_items.product_id, SUM(sales_invoice_items.quantity) as sold_90, SUM(sales_invoice_items.line_total) as sales_value_90, COUNT(DISTINCT sales_invoices.id) as invoice_count_90');

        $sales30 = DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->whereNull('sales_invoices.deleted_at')
            ->whereDate('sales_invoices.invoice_date', '>=', $from30)
            ->groupBy('sales_invoice_items.product_id')
            ->selectRaw('sales_invoice_items.product_id, SUM(sales_invoice_items.quantity) as sold_30');

        $stock = DB::table('batches')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->groupBy('product_id')
            ->selectRaw(
                'product_id,
                SUM(quantity_available) as stock_on_hand,
                SUM(CASE WHEN expires_at IS NOT NULL AND expires_at >= ? AND expires_at <= ? THEN quantity_available ELSE 0 END) as expiring_quantity,
                MIN(CASE WHEN quantity_available > 0 THEN expires_at ELSE NULL END) as nearest_expiry',
                [$today->toDateString(), $expiryTo],
            );

        return DB::table('products')
            ->leftJoinSub($sales90, 'sales90', 'sales90.product_id', '=', 'products.id')
            ->leftJoinSub($sales30, 'sales30', 'sales30.product_id', '=', 'products.id')
            ->leftJoinSub($stock, 'stock', 'stock.product_id', '=', 'products.id')
            ->leftJoin('companies', 'companies.id', '=', 'products.company_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.category_id')
            ->whereNull('products.deleted_at')
            ->where('products.is_active', true)
            ->when($filters->companyId, fn ($builder, int $companyId) => $builder->where('products.company_id', $companyId))
            ->when($filters->categoryId, fn ($builder, int $categoryId) => $builder->where('products.category_id', $categoryId))
            ->when($filters->search, function ($builder, string $search) {
                $keyword = '%'.strtolower($search).'%';
                $builder->where(function ($inner) use ($keyword) {
                    $inner->whereRaw('LOWER(products.name) LIKE ?', [$keyword])
                        ->orWhereRaw('LOWER(products.sku) LIKE ?', [$keyword])
                        ->orWhereRaw('LOWER(products.barcode) LIKE ?', [$keyword]);
                });
            })
            ->selectRaw(
                'products.id,
                products.name,
                products.sku,
                products.reorder_level,
                products.reorder_quantity,
                products.purchase_price,
                products.selling_price,
                companies.name as company,
                product_categories.name as category,
                COALESCE(stock.stock_on_hand, 0) as stock_on_hand,
                COALESCE(stock.expiring_quantity, 0) as expiring_quantity,
                stock.nearest_expiry,
                COALESCE(sales90.sold_90, 0) as sold_90,
                COALESCE(sales90.sales_value_90, 0) as sales_value_90,
                COALESCE(sales90.invoice_count_90, 0) as invoice_count_90,
                COALESCE(sales30.sold_30, 0) as sold_30'
            )
            ->orderBy('products.name')
            ->get();
    }
}
