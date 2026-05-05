<?php

namespace App\Modules\MR\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Modules\MR\Services\MrPerformanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="FIELD FORCE - MR Tracking",
 *     description="API endpoints for FIELD FORCE - MR Tracking"
 * )
 */
class MrPerformanceController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/mr/performance",
     *     summary="Api Mr Performance",
     *     tags={"FIELD FORCE - Performance"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function __invoke(Request $request, MrPerformanceService $service): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('mr.view') || $request->user()?->can('mr.visits.manage'), 403);

        return response()->json(['data' => $service->monthly($request->user(), $request->query())]);
    }
}
