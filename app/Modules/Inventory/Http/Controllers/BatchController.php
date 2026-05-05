<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Http\Controllers\ModularController;
use App\Modules\Inventory\Http\Requests\BatchRequest;
use App\Modules\Inventory\Http\Resources\BatchResource;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Services\BatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="INVENTORY - Products and Stock",
 *     description="API endpoints for INVENTORY - Products and Stock"
 * )
 */
class BatchController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/inventory/batches",
     *     summary="Api Batches Index",
     *     tags={"INVENTORY - Batches"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request, BatchService $service): JsonResponse
    {
        $result = $service->table($request, $request->user());

        return BatchResource::collection($result['batches'])
            ->additional(['summary' => $result['summary']])
            ->response();
    }

    /**
     * @OA\Get(
     *     path="/inventory/batches/options",
     *     summary="Api Inventory Batches Options",
     *     tags={"INVENTORY - Batches"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function options(Request $request, BatchService $service): JsonResponse
    {
        $batches = $service->options($request, $request->user());

        return response()->json([
            'data' => $batches->map(fn (Batch $batch) => [
                'id' => $batch->id,
                'product_id' => $batch->product_id,
                'product_name' => $batch->product?->name,
                'batch_no' => $batch->batch_no,
                'expires_at' => $batch->expires_at?->toDateString(),
                'quantity_available' => (float) $batch->quantity_available,
                'purchase_price' => (float) $batch->purchase_price,
                'mrp' => (float) $batch->mrp,
            ])->values(),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/inventory/batches",
     *     summary="Api Batches Store",
     *     tags={"INVENTORY - Batches"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(BatchRequest $request, BatchService $service): JsonResponse
    {
        $batch = $service->save($request->validated(), $request->user());

        return (new BatchResource($batch))
            ->additional(['message' => 'Batch saved.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Put(
     *     path="/inventory/batches/{batch}",
     *     summary="Api Batches Update",
     *     tags={"INVENTORY - Batches"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(BatchRequest $request, Batch $batch, BatchService $service): BatchResource
    {
        return new BatchResource($service->save($request->validated(), $request->user(), $batch));
    }

    /**
     * @OA\Delete(
     *     path="/inventory/batches/{batch}",
     *     summary="Api Batches Destroy",
     *     tags={"INVENTORY - Batches"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Batch $batch, BatchService $service): JsonResponse
    {
        $service->delete($batch, request()->user());

        return response()->json(['message' => 'Batch removed.']);
    }
}
