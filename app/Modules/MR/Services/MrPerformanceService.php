<?php

namespace App\Modules\MR\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MrPerformanceService
{
    public function monthly(?User $user = null, array $filters = []): array
    {
        $from = ! empty($filters['from']) ? CarbonImmutable::parse($filters['from'])->toDateString() : null;
        $to = ! empty($filters['to']) ? CarbonImmutable::parse($filters['to'])->toDateString() : null;
        $representativeId = isset($filters['medical_representative_id']) ? (int) $filters['medical_representative_id'] : null;

        if ($user && $user->hasRole('MR') && (int) $user->medical_representative_id > 0) {
            $representativeId = (int) $user->medical_representative_id;
        }

        $visitTotals = DB::table('representative_visits')
            ->whereNull('deleted_at')
            ->when($from, fn ($query) => $query->whereDate('visit_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('visit_date', '<=', $to))
            ->when($representativeId, fn ($query) => $query->where('medical_representative_id', $representativeId))
            ->groupBy('medical_representative_id')
            ->selectRaw('medical_representative_id, COUNT(*) as visits, COALESCE(SUM(order_value), 0) as visit_order_value');

        $invoiceTotals = DB::table('sales_invoices')
            ->whereNull('deleted_at')
            ->when($from, fn ($query) => $query->whereDate('invoice_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('invoice_date', '<=', $to))
            ->when($representativeId, fn ($query) => $query->where('medical_representative_id', $representativeId))
            ->groupBy('medical_representative_id')
            ->selectRaw('medical_representative_id, COUNT(*) as invoices, COALESCE(SUM(grand_total), 0) as invoiced_value');

        $rows = DB::table('medical_representatives')
            ->whereNull('medical_representatives.deleted_at')
            ->when($representativeId, fn ($query) => $query->where('medical_representatives.id', $representativeId))
            ->leftJoin('areas', 'areas.id', '=', 'medical_representatives.area_id')
            ->leftJoin('divisions', 'divisions.id', '=', 'medical_representatives.division_id')
            ->leftJoinSub($visitTotals, 'visit_totals', 'visit_totals.medical_representative_id', '=', 'medical_representatives.id')
            ->leftJoinSub($invoiceTotals, 'invoice_totals', 'invoice_totals.medical_representative_id', '=', 'medical_representatives.id')
            ->selectRaw('
                medical_representatives.id,
                medical_representatives.name,
                areas.name as area,
                divisions.name as division,
                medical_representatives.monthly_target,
                medical_representatives.is_active,
                COALESCE(visit_totals.visits, 0) as visits,
                COALESCE(visit_totals.visit_order_value, 0) as visit_order_value,
                COALESCE(invoice_totals.invoices, 0) as invoices,
                COALESCE(invoice_totals.invoiced_value, 0) as invoiced_value
            ')
            ->orderByDesc('invoiced_value')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'area' => $row->area,
                'division' => $row->division,
                'monthly_target' => (float) $row->monthly_target,
                'visits' => (int) $row->visits,
                'visit_order_value' => (float) $row->visit_order_value,
                'invoices' => (int) $row->invoices,
                'invoiced_value' => (float) $row->invoiced_value,
                'achievement_percent' => (float) $row->monthly_target > 0
                    ? round(((float) $row->invoiced_value / (float) $row->monthly_target) * 100, 2)
                    : 0,
                'is_active' => (bool) $row->is_active,
            ])
            ->all();

        return [
            'period' => $this->periodLabel($from, $to),
            'filters' => [
                'from' => $from,
                'to' => $to,
                'medical_representative_id' => $representativeId,
            ],
            'totals' => [
                'active_mrs' => collect($rows)->where('is_active', true)->count(),
                'visits' => collect($rows)->sum('visits'),
                'visit_order_value' => collect($rows)->sum('visit_order_value'),
                'invoiced_value' => collect($rows)->sum('invoiced_value'),
                'target_value' => collect($rows)->sum('monthly_target'),
            ],
            'rows' => $rows,
        ];
    }

    private function periodLabel(?string $from, ?string $to): string
    {
        if (! $from && ! $to) {
            return 'All time';
        }

        $fromLabel = $from ? CarbonImmutable::parse($from)->format('j M Y') : 'Start';
        $toLabel = $to ? CarbonImmutable::parse($to)->format('j M Y') : 'Today';

        return $fromLabel.' - '.$toLabel;
    }
}
