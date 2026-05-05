<?php

namespace App\Modules\ImportExport\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Modules\ImportExport\Services\ExportService;
use App\Modules\Inventory\Models\Product;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="IMPORT EXPORT - Exports",
 *     description="API endpoints for Excel and PDF exports"
 * )
 */
class ExportController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/exports/inventory/masters/{master}/{format}",
     *     summary="Export inventory master data",
     *     tags={"IMPORT EXPORT - Exports"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function inventoryMaster(Request $request, string $master, string $format, ExportService $service)
    {
        $this->authorize('viewAny', Product::class);

        return $service->inventoryMaster($request, $master, $format);
    }

    /**
     * @OA\Get(
     *     path="/exports/inventory/products/{format}",
     *     summary="Export inventory products",
     *     tags={"IMPORT EXPORT - Exports"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function inventoryProducts(Request $request, string $format, ExportService $service)
    {
        $this->authorize('viewAny', Product::class);

        return $service->inventoryProducts($request, $format);
    }

    /**
     * @OA\Get(
     *     path="/exports/inventory/batches/{format}",
     *     summary="Export inventory batches",
     *     tags={"IMPORT EXPORT - Exports"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function inventoryBatches(Request $request, string $format, ExportService $service)
    {
        $this->authorize('viewAny', Product::class);

        return $service->inventoryBatches($request, $format);
    }

    /**
     * @OA\Get(
     *     path="/exports/{dataset}/{format}",
     *     summary="Export operational dataset",
     *     tags={"IMPORT EXPORT - Exports"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function dataset(Request $request, string $dataset, string $format, ExportService $service)
    {
        return $service->dataset($request, $dataset, $format);
    }
}
