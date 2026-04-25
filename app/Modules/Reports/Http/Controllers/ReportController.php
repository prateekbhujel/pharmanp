<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __invoke(string $report, Request $request, ReportService $service): JsonResponse
    {
        $accountingReports = ['day-book', 'cash-book', 'bank-book', 'ledger', 'trial-balance'];
        $mrReports = ['mr-performance'];

        if (in_array($report, $accountingReports, true)) {
            abort_unless(
                $request->user()?->is_owner
                || $request->user()?->can('accounting.books.view')
                || ($report === 'trial-balance' && $request->user()?->can('accounting.trial_balance.view')),
                403
            );
        } elseif (in_array($report, $mrReports, true)) {
            abort_unless(
                $request->user()?->is_owner
                || $request->user()?->can('reports.view')
                || $request->user()?->can('mr.view')
                || $request->user()?->can('mr.visits.manage'),
                403
            );
        } else {
            abort_unless($request->user()?->is_owner || $request->user()?->can('reports.view'), 403);
        }

        return response()->json($service->run($report, $request));
    }
}
