<?php

namespace App\Modules\MR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MR\Contracts\MrPerformanceServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MrPerformanceController extends Controller
{
    public function __invoke(Request $request, MrPerformanceServiceInterface $service): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('mr.view') || $request->user()?->can('mr.visits.manage'), 403);

        return response()->json(['data' => $service->monthly($request->user(), $request->query())]);
    }
}
