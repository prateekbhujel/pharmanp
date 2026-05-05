<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Modules\Core\Http\Resources\DashboardSummaryResource;
use App\Modules\Core\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="CORE - Platform",
 *     description="API endpoints for CORE - Platform"
 * )
 */
class DashboardController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/dashboard/summary",
     *     summary="Api Dashboard Summary",
     *     tags={"DASHBOARD - Overview"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function __invoke(Request $request, DashboardService $service): JsonResponse
    {
        return DashboardSummaryResource::make($service->summary($request->query(), $request->user()))->response();
    }
}
