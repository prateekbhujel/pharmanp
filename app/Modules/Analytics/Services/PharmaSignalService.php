<?php

namespace App\Modules\Analytics\Services;

use App\Core\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Rubix\ML\Clusterers\KMeans;
use Rubix\ML\Datasets\Unlabeled;

class PharmaSignalService
{
    public function inventorySignals(Request $request, int $perPage = 20): array
    {
        $today = CarbonImmutable::today();
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

        $query = DB::table('products')
            ->leftJoinSub($sales90, 'sales90', 'sales90.product_id', '=', 'products.id')
            ->leftJoinSub($sales30, 'sales30', 'sales30.product_id', '=', 'products.id')
            ->leftJoinSub($stock, 'stock', 'stock.product_id', '=', 'products.id')
            ->leftJoin('companies', 'companies.id', '=', 'products.company_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.category_id')
            ->whereNull('products.deleted_at')
            ->where('products.is_active', true)
            ->when($request->filled('company_id'), fn ($builder) => $builder->where('products.company_id', $request->integer('company_id')))
            ->when($request->filled('category_id'), fn ($builder) => $builder->where('products.category_id', $request->integer('category_id')))
            ->when($request->filled('search'), function ($builder) use ($request) {
                $search = '%'.strtolower((string) $request->query('search')).'%';
                $builder->where(function ($inner) use ($search) {
                    $inner->whereRaw('LOWER(products.name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(products.sku) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(products.barcode) LIKE ?', [$search]);
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
            ->orderBy('products.name');

        $rows = collect($query->get())
            ->map(fn ($row) => $this->scoreRow((array) $row, $today))
            ->values()
            ->all();

        $rubix = $this->clusterMovement($rows);
        foreach ($rows as $index => $row) {
            $rows[$index]['movement_group'] = $rubix['labels'][$index] ?? $row['movement_group'];
            $rows[$index]['engine'] = $rubix['enabled'] ? 'rubixml+kpi' : 'kpi';
        }

        if ($request->filled('signal')) {
            $signal = (string) $request->query('signal');
            $rows = array_values(array_filter($rows, fn ($row) => $row['reorder_signal'] === $signal || $row['expiry_signal'] === $signal || $row['movement_group'] === $signal));
        }

        usort($rows, fn ($a, $b) => [$b['risk_score'], $a['name']] <=> [$a['risk_score'], $b['name']]);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = array_slice($rows, ($page - 1) * $perPage, $perPage);
        $paginator = new LengthAwarePaginator($items, count($rows), $perPage, $page);

        return [
            'data' => $paginator->items(),
            'meta' => ApiResponse::paginationMeta($paginator),
            'summary' => [
                'products_scored' => count($rows),
                'urgent_reorder' => collect($rows)->where('reorder_signal', 'urgent_reorder')->count(),
                'reorder_soon' => collect($rows)->where('reorder_signal', 'reorder_soon')->count(),
                'overstock' => collect($rows)->where('reorder_signal', 'overstock')->count(),
                'expiry_risk' => collect($rows)->filter(fn ($row) => in_array($row['expiry_signal'], ['expiry_urgent', 'expiry_watch'], true))->count(),
                'rubix_groups' => $rubix['enabled'] ? 3 : 0,
            ],
            'engine' => [
                'name' => 'PharmaNP Smart Signals',
                'rubixml' => $rubix['enabled'],
                'fallback' => $rubix['enabled'] ? null : $rubix['reason'],
                'period_days' => 90,
                'shared_hosting_safe' => true,
            ],
        ];
    }

    private function scoreRow(array $row, CarbonImmutable $today): array
    {
        $stock = round((float) $row['stock_on_hand'], 3);
        $sold90 = round((float) $row['sold_90'], 3);
        $sold30 = round((float) $row['sold_30'], 3);
        $avgDaily = $sold90 > 0 ? round($sold90 / 90, 4) : 0.0;
        $daysCover = $avgDaily > 0 ? round($stock / $avgDaily, 1) : null;
        $reorderLevel = (float) $row['reorder_level'];
        $expiring = round((float) $row['expiring_quantity'], 3);
        $marginPercent = $this->marginPercent((float) $row['purchase_price'], (float) $row['selling_price']);
        $expiryDays = $row['nearest_expiry'] ? $today->diffInDays(CarbonImmutable::parse($row['nearest_expiry']), false) : null;

        $reorderSignal = $this->reorderSignal($stock, $sold90, $avgDaily, $daysCover, $reorderLevel);
        $expirySignal = $this->expirySignal($expiring, $expiryDays);
        $riskScore = $this->riskScore($reorderSignal, $expirySignal, $daysCover, $sold90, $expiring);

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'sku' => $row['sku'] ?: '-',
            'company' => $row['company'] ?: '-',
            'category' => $row['category'] ?: '-',
            'stock_on_hand' => $stock,
            'reorder_level' => $reorderLevel,
            'sold_30' => $sold30,
            'sold_90' => $sold90,
            'avg_daily_sales' => $avgDaily,
            'days_cover' => $daysCover,
            'expiring_quantity' => $expiring,
            'nearest_expiry' => $row['nearest_expiry'],
            'margin_percent' => $marginPercent,
            'movement_group' => $sold90 <= 0 ? 'dead_stock' : ($sold30 >= max(1, $sold90 * 0.45) ? 'fast_moving' : 'steady'),
            'reorder_signal' => $reorderSignal,
            'expiry_signal' => $expirySignal,
            'risk_score' => $riskScore,
            'recommendation' => $this->recommendation($reorderSignal, $expirySignal, $daysCover, $sold90),
        ];
    }

    private function clusterMovement(array $rows): array
    {
        if (count($rows) < 6 || ! class_exists(KMeans::class)) {
            return ['enabled' => false, 'reason' => 'Need at least 6 products with movement data for clustering.', 'labels' => []];
        }

        $samples = array_map(fn ($row) => [
            (float) $row['sold_90'],
            (float) $row['sold_30'],
            (float) $row['stock_on_hand'],
            (float) ($row['days_cover'] ?? 999),
            (float) $row['expiring_quantity'],
            (float) $row['margin_percent'],
        ], $rows);

        try {
            $clusterer = new KMeans(3, 64, 80);
            $dataset = Unlabeled::quick($samples);
            $clusterer->train($dataset);
            $clusters = $clusterer->predict($dataset);
        } catch (\Throwable $exception) {
            return ['enabled' => false, 'reason' => $exception->getMessage(), 'labels' => []];
        }

        $averages = [];
        foreach ($clusters as $index => $cluster) {
            $averages[$cluster][] = (float) $rows[$index]['sold_90'];
        }

        $ranked = collect($averages)
            ->map(fn ($values) => array_sum($values) / max(1, count($values)))
            ->sort()
            ->keys()
            ->values()
            ->all();

        $map = [
            $ranked[0] ?? null => 'slow_moving',
            $ranked[1] ?? null => 'steady',
            $ranked[2] ?? null => 'fast_moving',
        ];

        return [
            'enabled' => true,
            'reason' => null,
            'labels' => array_map(fn ($cluster) => $map[$cluster] ?? 'steady', $clusters),
        ];
    }

    private function reorderSignal(float $stock, float $sold90, float $avgDaily, ?float $daysCover, float $reorderLevel): string
    {
        if ($stock <= 0 || $stock <= $reorderLevel || ($daysCover !== null && $daysCover <= 7)) {
            return 'urgent_reorder';
        }

        if ($daysCover !== null && $daysCover <= 21) {
            return 'reorder_soon';
        }

        if (($sold90 <= 0 && $stock > 0) || ($avgDaily > 0 && $daysCover !== null && $daysCover >= 180)) {
            return 'overstock';
        }

        return 'ok';
    }

    private function expirySignal(float $expiringQuantity, ?float $expiryDays): string
    {
        if ($expiringQuantity <= 0 || $expiryDays === null) {
            return 'none';
        }

        if ($expiryDays <= 30) {
            return 'expiry_urgent';
        }

        return 'expiry_watch';
    }

    private function riskScore(string $reorderSignal, string $expirySignal, ?float $daysCover, float $sold90, float $expiring): int
    {
        $score = match ($reorderSignal) {
            'urgent_reorder' => 70,
            'reorder_soon' => 45,
            'overstock' => 35,
            default => 10,
        };

        $score += match ($expirySignal) {
            'expiry_urgent' => 25,
            'expiry_watch' => 15,
            default => 0,
        };

        if ($daysCover !== null && $daysCover <= 3 && $sold90 > 0) {
            $score += 10;
        }

        if ($expiring > 0 && $sold90 <= 0) {
            $score += 10;
        }

        return min(100, $score);
    }

    private function recommendation(string $reorderSignal, string $expirySignal, ?float $daysCover, float $sold90): string
    {
        if ($expirySignal === 'expiry_urgent') {
            return 'Move expiring stock first, discount if needed, and block excess reorder.';
        }

        if ($reorderSignal === 'urgent_reorder') {
            return 'Reorder now or transfer stock from another branch before selling out.';
        }

        if ($reorderSignal === 'reorder_soon') {
            return 'Prepare purchase order based on recent daily sales and supplier lead time.';
        }

        if ($reorderSignal === 'overstock') {
            return $sold90 > 0
                ? 'Reduce purchase quantity; current stock covers too many days.'
                : 'No recent sales. Review demand, visibility, expiry, and product duplication.';
        }

        return $daysCover === null ? 'No sales history yet; watch first purchase cycle.' : 'Stock level looks acceptable.';
    }

    private function marginPercent(float $purchasePrice, float $sellingPrice): float
    {
        if ($sellingPrice <= 0) {
            return 0.0;
        }

        return round((($sellingPrice - $purchasePrice) / $sellingPrice) * 100, 2);
    }
}
