<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Modules\Reports\Http\Requests\ReportRunRequest;
use App\Modules\Reports\Services\ReportExportService;
use App\Modules\Reports\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="REPORTS - Analytics and Exports",
 *     description="API endpoints for REPORTS - Analytics and Exports"
 * )
 */
class ReportController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/reports/{report}",
     *     summary="Api Reports Run",
     *     tags={"REPORTS - Operational Reports"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function __invoke(string $report, ReportRunRequest $request, ReportService $service): JsonResponse
    {
        $payload = $service->run($report, $request);

        return $this->success($payload['data'] ?? [], 'Report retrieved successfully.')
            ->setData([
                'status' => 'success',
                'code' => 200,
                'message' => 'Report retrieved successfully.',
                ...$payload,
            ]);
    }

    /**
     * @OA\Get(
     *     path="/reports/{report}/export/{format}",
     *     summary="Api Reports Export",
     *     tags={"REPORTS - Operational Reports"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function export(string $report, string $format, Request $request, ReportExportService $service)
    {
        return $service->export($report, $format, $request);
    }
}
