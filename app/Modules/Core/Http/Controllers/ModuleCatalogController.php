<?php

namespace App\Modules\Core\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\Modules\ModuleRegistry;
use App\Http\Controllers\ModularController;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="CORE - Platform",
 *     description="API endpoints for CORE - Platform"
 * )
 */
class ModuleCatalogController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/modules",
     *     summary="Api Modules Index",
     *     tags={"CORE - Module Catalog"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function __invoke(ModuleRegistry $modules): JsonResponse
    {
        return response()->json([
            'data' => $modules->toArray(),
        ]);
    }
}
