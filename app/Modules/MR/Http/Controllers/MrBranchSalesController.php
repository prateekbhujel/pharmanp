<?php

namespace App\Modules\MR\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Product-level sales breakdown per MR and per branch.
 * HQ sees all branches. Branch manager sees their branch. MR sees their own data.
 */
class MrBranchSalesController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user   = $request->user();
        $params = $request->query();

        $from   = $params['from'] ?? now()->startOfMonth()->toDateString();
        $to     = $params['to']   ?? now()->toDateString();
        $branchId     = isset($params['branch_id'])   ? (int) $params['branch_id']   : null;
        $representativeId = isset($params['mr_id']) ? (int) $params['mr_id']   : null;
        $productId    = isset($params['product_id']) ? (int) $params['product_id'] : null;

        // Scope by role: MR sees only themselves, branch manager sees own branch
        if ($user->hasRole('MR') && $user->medical_representative_id) {
            $representativeId = (int) $user->medical_representative_id;
        }

        // product-level sales rows joined back to MR and branch
        $rows = DB::table('sales_invoice_items as sii')
            ->join('sales_invoices as si', 'si.id', '=', 'sii.sales_invoice_id')
            ->join('products as p', 'p.id', '=', 'sii.product_id')
            ->leftJoin('medical_representatives as mr', 'mr.id', '=', 'si.medical_representative_id')
            ->leftJoin('branches as b', 'b.id', '=', 'mr.branch_id')
            ->whereNull('si.deleted_at')
            ->whereNull('sii.deleted_at')
            ->whereBetween('si.invoice_date', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('b.id', $branchId))
            ->when($representativeId, fn ($q) => $q->where('mr.id', $representativeId))
            ->when($productId, fn ($q) => $q->where('p.id', $productId))
            ->selectRaw('
                b.id          as branch_id,
                b.name        as branch_name,
                mr.id         as mr_id,
                mr.name       as mr_name,
                p.id          as product_id,
                p.name        as product_name,
                SUM(sii.quantity)   as total_qty,
                SUM(sii.total_price) as total_value
            ')
            ->groupBy('b.id', 'b.name', 'mr.id', 'mr.name', 'p.id', 'p.name')
            ->orderByDesc('total_value')
            ->get();

        // Roll up totals per branch
        $branchSummary = $rows->groupBy('branch_id')->map(function ($items, $branchId) {
            $first = $items->first();
            return [
                'branch_id'   => $branchId,
                'branch_name' => $first->branch_name ?? 'Unassigned',
                'total_value' => (float) $items->sum('total_value'),
                'total_qty'   => (float) $items->sum('total_qty'),
                'mr_count'    => $items->pluck('mr_id')->filter()->unique()->count(),
                'top_product' => $items->sortByDesc('total_value')->first()?->product_name,
            ];
        })->values();

        // Roll up totals per MR
        $mrSummary = $rows->groupBy('mr_id')->map(function ($items, $mrId) {
            $first = $items->first();
            return [
                'mr_id'       => $mrId,
                'mr_name'     => $first->mr_name ?? 'Unassigned',
                'branch_id'   => $first->branch_id,
                'branch_name' => $first->branch_name ?? 'Unassigned',
                'total_value' => (float) $items->sum('total_value'),
                'total_qty'   => (float) $items->sum('total_qty'),
                'products'    => $items->map(fn ($row) => [
                    'product_id'   => $row->product_id,
                    'product_name' => $row->product_name,
                    'qty'          => (float) $row->total_qty,
                    'value'        => (float) $row->total_value,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'data' => [
                'period'         => $from . ' – ' . $to,
                'grand_total'    => (float) $rows->sum('total_value'),
                'branch_summary' => $branchSummary,
                'mr_summary'     => $mrSummary,
                'rows'           => $rows->map(fn ($row) => [
                    'branch_id'    => $row->branch_id,
                    'branch_name'  => $row->branch_name ?? 'Unassigned',
                    'mr_id'        => $row->mr_id,
                    'mr_name'      => $row->mr_name ?? 'Unassigned',
                    'product_id'   => $row->product_id,
                    'product_name' => $row->product_name,
                    'total_qty'    => (float) $row->total_qty,
                    'total_value'  => (float) $row->total_value,
                ])->values(),
            ],
        ]);
    }
}
