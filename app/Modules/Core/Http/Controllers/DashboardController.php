<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $service): JsonResponse
    {
        abort_unless(
            $request->user()?->is_owner
            || $request->user()?->can('dashboard.view')
            || $request->user()?->can('mr.view')
            || $request->user()?->can('mr.visits.manage'),
            403
        );

        return response()->json(['data' => $service->summary($request->query(), $request->user())]);
    }
}
