<?php

namespace App\Modules\Reports\Services;

use App\Core\Support\ApiResponse;
use App\Core\Support\MoneyAmount;
use App\Modules\Setup\Models\Target;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TargetAchievementService
{
    public function achievement(Request $request, int $perPage): array
    {
        $query = Target::query()
            ->where('status', 'active')
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('company_id', $companyId))
            ->when($request->filled('target_type'), fn ($builder) => $builder->where('target_type', $request->query('target_type')))
            ->when($request->filled('target_period'), fn ($builder) => $builder->where('target_period', $request->query('target_period')))
            ->when($request->filled('target_level'), fn ($builder) => $builder->where('target_level', $request->query('target_level')))
            ->when($request->filled('branch_id'), fn ($builder) => $builder->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('area_id'), fn ($builder) => $builder->where('area_id', $request->integer('area_id')))
            ->when($request->filled('division_id'), fn ($builder) => $builder->where('division_id', $request->integer('division_id')))
            ->when($request->filled('employee_id'), fn ($builder) => $builder->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('product_id'), fn ($builder) => $builder->where('product_id', $request->integer('product_id')))
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        $page = $query->paginate($perPage);
        $rows = collect($page->items())->map(function (Target $target) {
            $achieved = $this->achievedAmount($target);
            $targetCents = MoneyAmount::cents($target->target_amount);
            $achievedCents = MoneyAmount::cents($achieved);

            return [
                'id' => $target->id,
                'target_type' => $target->target_type,
                'target_period' => $target->target_period,
                'target_level' => $target->target_level,
                'target_amount' => MoneyAmount::fromCents($targetCents),
                'target_quantity' => (string) ($target->target_quantity ?? '0'),
                'achieved_amount' => $achieved,
                'achievement_percent' => $targetCents > 0 ? ($achievedCents / $targetCents) * 100 : null,
                'start_date' => $target->start_date?->toDateString(),
                'end_date' => $target->end_date?->toDateString(),
                'branch_id' => $target->branch_id,
                'area_id' => $target->area_id,
                'division_id' => $target->division_id,
                'employee_id' => $target->employee_id,
                'product_id' => $target->product_id,
            ];
        })->values();

        $paginator = new LengthAwarePaginator($rows, $page->total(), $page->perPage(), $page->currentPage());

        return [
            'data' => $paginator->items(),
            'meta' => ApiResponse::paginationMeta($paginator),
            'summary' => [
                'target_amount' => MoneyAmount::fromCents($rows->sum(fn (array $row) => MoneyAmount::cents($row['target_amount']))),
                'achieved_amount' => MoneyAmount::fromCents($rows->sum(fn (array $row) => MoneyAmount::cents($row['achieved_amount']))),
            ],
        ];
    }

    private function achievedAmount(Target $target): string
    {
        $base = DB::table('sales_invoices')
            ->whereNull('sales_invoices.deleted_at')
            ->whereBetween('sales_invoices.invoice_date', [$target->start_date->toDateString(), $target->end_date->toDateString()])
            ->when($target->tenant_id, fn ($builder, $tenantId) => $builder->where('sales_invoices.tenant_id', $tenantId))
            ->when($target->company_id, fn ($builder, $companyId) => $builder->where('sales_invoices.company_id', $companyId));

        if ($target->target_level === 'product') {
            return MoneyAmount::decimal($base
                ->join('sales_invoice_items', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
                ->where('sales_invoice_items.product_id', $target->product_id)
                ->sum('sales_invoice_items.line_total'));
        }

        if ($target->target_level === 'division') {
            return MoneyAmount::decimal($base
                ->join('sales_invoice_items', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
                ->join('products', 'products.id', '=', 'sales_invoice_items.product_id')
                ->where('products.division_id', $target->division_id)
                ->sum('sales_invoice_items.line_total'));
        }

        if ($target->target_level === 'employee') {
            return MoneyAmount::decimal($base
                ->join('medical_representatives', 'medical_representatives.id', '=', 'sales_invoices.medical_representative_id')
                ->where('medical_representatives.employee_id', $target->employee_id)
                ->sum('sales_invoices.grand_total'));
        }

        if ($target->target_level === 'area') {
            return MoneyAmount::decimal($base
                ->join('medical_representatives', 'medical_representatives.id', '=', 'sales_invoices.medical_representative_id')
                ->where('medical_representatives.area_id', $target->area_id)
                ->sum('sales_invoices.grand_total'));
        }

        return MoneyAmount::decimal($base->sum('sales_invoices.grand_total'));
    }
}
