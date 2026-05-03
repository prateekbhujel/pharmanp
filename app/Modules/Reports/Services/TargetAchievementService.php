<?php

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Contracts\TargetAchievementServiceInterface;
use App\Modules\Setup\Models\Target;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TargetAchievementService implements TargetAchievementServiceInterface
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
            $targetAmount = (float) $target->target_amount;

            return [
                'id' => $target->id,
                'target_type' => $target->target_type,
                'target_period' => $target->target_period,
                'target_level' => $target->target_level,
                'target_amount' => $targetAmount,
                'target_quantity' => (float) $target->target_quantity,
                'achieved_amount' => $achieved,
                'achievement_percent' => $targetAmount > 0 ? ($achieved / $targetAmount) * 100 : null,
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
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'summary' => [
                'target_amount' => (float) $rows->sum('target_amount'),
                'achieved_amount' => (float) $rows->sum('achieved_amount'),
            ],
        ];
    }

    private function achievedAmount(Target $target): float
    {
        $base = DB::table('sales_invoices')
            ->whereNull('sales_invoices.deleted_at')
            ->whereBetween('sales_invoices.invoice_date', [$target->start_date->toDateString(), $target->end_date->toDateString()])
            ->when($target->tenant_id, fn ($builder, $tenantId) => $builder->where('sales_invoices.tenant_id', $tenantId))
            ->when($target->company_id, fn ($builder, $companyId) => $builder->where('sales_invoices.company_id', $companyId));

        if ($target->target_level === 'product') {
            return (float) $base
                ->join('sales_invoice_items', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
                ->where('sales_invoice_items.product_id', $target->product_id)
                ->sum('sales_invoice_items.line_total');
        }

        if ($target->target_level === 'division') {
            return (float) $base
                ->join('sales_invoice_items', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
                ->join('products', 'products.id', '=', 'sales_invoice_items.product_id')
                ->where('products.division_id', $target->division_id)
                ->sum('sales_invoice_items.line_total');
        }

        if ($target->target_level === 'employee') {
            return (float) $base
                ->join('medical_representatives', 'medical_representatives.id', '=', 'sales_invoices.medical_representative_id')
                ->where('medical_representatives.employee_id', $target->employee_id)
                ->sum('sales_invoices.grand_total');
        }

        if ($target->target_level === 'area') {
            return (float) $base
                ->join('medical_representatives', 'medical_representatives.id', '=', 'sales_invoices.medical_representative_id')
                ->where('medical_representatives.area_id', $target->area_id)
                ->sum('sales_invoices.grand_total');
        }

        return (float) $base->sum('sales_invoices.grand_total');
    }
}
