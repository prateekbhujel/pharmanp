<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Http\Controllers\ModularController;
use App\Modules\Setup\Services\FeatureCatalogService;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class FeatureCatalogController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/setup/features",
     *     summary="Api Setup Features",
     *     tags={"SETUP - Features"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function __invoke(FeatureCatalogService $service): JsonResponse
    {
        return response()->json(['data' => $service->grouped()]);
    }
}
