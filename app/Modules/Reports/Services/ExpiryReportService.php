<?php

namespace App\Modules\Reports\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\Database\SqlDialect;
use App\Core\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpiryReportService
{
    public function __construct(private readonly SqlDialect $sql) {}

    public function buckets(Request $request, int $perPage): array
    {
        $days = $this->daysRemainingExpression('batches.expires_at');

        $query = DB::table('batches')
            ->join('products', 'products.id', '=', 'batches.product_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'batches.supplier_id')
            ->leftJoin('divisions', 'divisions.id', '=', 'products.division_id')
            ->whereNull('batches.deleted_at')
            ->where('batches.quantity_available', '>', 0)
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('batches.tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('batches.company_id', $companyId))
            ->when($request->filled('product_id'), fn ($builder) => $builder->where('batches.product_id', $request->integer('product_id')))
            ->when($request->filled('supplier_id'), fn ($builder) => $builder->where('batches.supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('division_id'), fn ($builder) => $builder->where('products.division_id', $request->integer('division_id')));

        if ($request->filled('bucket')) {
            match ($request->query('bucket')) {
                'expired' => $query->whereRaw("{$days} < 0"),
                '30' => $query->whereRaw("{$days} >= 0 AND {$days} <= 30"),
                '60' => $query->whereRaw("{$days} > 30 AND {$days} <= 60"),
                '90' => $query->whereRaw("{$days} > 60 AND {$days} <= 90"),
                default => null,
            };
        }

        $page = (clone $query)
            ->orderBy('batches.expires_at')
            ->orderBy('products.name')
            ->selectRaw("
                products.product_code,
                products.name as product,
                products.hs_code,
                divisions.name as division,
                suppliers.name as supplier,
                batches.batch_no,
                batches.quantity_available as remaining_quantity,
                batches.expires_at as expiry_date,
                {$days} as days_remaining,
                batches.mrp
            ")
            ->paginate($perPage);

        return [
            'data' => collect($page->items())->map(function ($row) {
                $remaining = (int) $row->days_remaining;

                return [
                    ...get_object_vars($row),
                    'status' => match (true) {
                        $remaining < 0 => 'expired',
                        $remaining <= 30 => 'expiring_30',
                        $remaining <= 60 => 'expiring_60',
                        $remaining <= 90 => 'expiring_90',
                        default => 'healthy',
                    },
                ];
            })->values(),
            'meta' => ApiResponse::paginationMeta($page),
            'summary' => $this->summary($query, $days),
        ];
    }

    private function summary($query, string $daysExpression): array
    {
        $row = (clone $query)
            ->selectRaw("
                SUM(CASE WHEN {$daysExpression} < 0 THEN batches.quantity_available ELSE 0 END) as expired_quantity,
                SUM(CASE WHEN {$daysExpression} >= 0 AND {$daysExpression} <= 30 THEN batches.quantity_available ELSE 0 END) as expiring_30_quantity,
                SUM(CASE WHEN {$daysExpression} > 30 AND {$daysExpression} <= 60 THEN batches.quantity_available ELSE 0 END) as expiring_60_quantity,
                SUM(CASE WHEN {$daysExpression} > 60 AND {$daysExpression} <= 90 THEN batches.quantity_available ELSE 0 END) as expiring_90_quantity,
                COUNT(*) as batch_count
            ")
            ->first();

        return [
            'batch_count' => (int) ($row->batch_count ?? 0),
            'expired_quantity' => (float) ($row->expired_quantity ?? 0),
            'expiring_30_quantity' => (float) ($row->expiring_30_quantity ?? 0),
            'expiring_60_quantity' => (float) ($row->expiring_60_quantity ?? 0),
            'expiring_90_quantity' => (float) ($row->expiring_90_quantity ?? 0),
        ];
    }

    private function daysRemainingExpression(string $dateColumn): string
    {
        return $this->sql->daysFromToday($dateColumn);
    }
}
