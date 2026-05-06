<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Modules\Inventory\Http\Resources\StockMovementResource;
use App\Modules\Inventory\Services\StockMovementLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="INVENTORY - Products and Stock",
 *     description="API endpoints for INVENTORY - Products and Stock"
 * )
 */
class StockMovementController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/inventory/stock-movements",
     *     summary="Api Inventory Stock Movements Index",
     *     tags={"INVENTORY - Stock Movements"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request, StockMovementLedgerService $service): JsonResponse
    {
        abort_unless(
            $request->user()?->is_owner ||
            $request->user()?->can('inventory.batches.view') ||
            $request->user()?->can('inventory.products.view'),
            403,
        );

        $result = $service->table($request, $request->user());

        return StockMovementResource::collection($result['movements'])
            ->additional([
                'summary' => $result['summary'],
            ])
            ->response();
    }
}
