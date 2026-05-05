<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Core\Query\TableQueryApplier;
use App\Http\Controllers\ModularController;
use App\Modules\Inventory\Http\Requests\StockAdjustmentRequest;
use App\Modules\Inventory\Http\Resources\StockAdjustmentResource;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Services\StockAdjustmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="INVENTORY - Products and Stock",
 *     description="API endpoints for INVENTORY - Products and Stock"
 * )
 */
class StockAdjustmentController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/inventory/stock-adjustments",
     *     summary="Api Stock Adjustments Index",
     *     tags={"INVENTORY - Stock Adjustments"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request, TableQueryApplier $tables): JsonResponse
    {
        $search = trim((string) $request->query('search'));

        $query = StockAdjustment::query()
            ->with(['product:id,name', 'batch:id,batch_no,quantity_available', 'adjustedBy:id,name']);
        $tables->operatingContext($query, $request->user());

        $adjustments = $query
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('adjustment_type', 'like', '%'.$search.'%')
                        ->orWhere('reason', 'like', '%'.$search.'%')
                        ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('batch', fn (Builder $batch) => $batch->where('batch_no', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->filled('product_id'), fn (Builder $builder) => $builder->where('product_id', $request->integer('product_id')))
            ->when($request->filled('batch_id'), fn (Builder $builder) => $builder->where('batch_id', $request->integer('batch_id')))
            ->when($request->filled('adjustment_type'), fn (Builder $builder) => $builder->where('adjustment_type', $request->query('adjustment_type')))
            ->when($request->filled('from'), fn (Builder $builder) => $builder->whereDate('adjustment_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn (Builder $builder) => $builder->whereDate('adjustment_date', '<=', $request->query('to')))
            ->orderByDesc('adjustment_date')
            ->orderByDesc('id')
            ->paginate(min(100, max(5, $request->integer('per_page', 15))));

        return StockAdjustmentResource::collection($adjustments)->response();
    }

    /**
     * @OA\Post(
     *     path="/inventory/stock-adjustments",
     *     summary="Api Stock Adjustments Store",
     *     tags={"INVENTORY - Stock Adjustments"},
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
    public function store(StockAdjustmentRequest $request, StockAdjustmentService $service): JsonResponse
    {
        $adjustment = $service->save($request->validated(), $request->user());

        return (new StockAdjustmentResource($adjustment))
            ->additional(['message' => 'Stock adjustment posted.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Put(
     *     path="/inventory/stock-adjustments/{adjustment}",
     *     summary="Api Stock Adjustments Update",
     *     tags={"INVENTORY - Stock Adjustments"},
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
    public function update(StockAdjustmentRequest $request, StockAdjustment $adjustment, StockAdjustmentService $service): StockAdjustmentResource
    {
        $service->assertAccessible($adjustment, $request->user(), 'update');

        return new StockAdjustmentResource($service->save($request->validated(), $request->user(), $adjustment));
    }

    /**
     * @OA\Delete(
     *     path="/inventory/stock-adjustments/{adjustment}",
     *     summary="Api Stock Adjustments Destroy",
     *     tags={"INVENTORY - Stock Adjustments"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Request $request, StockAdjustment $adjustment, StockAdjustmentService $service): JsonResponse
    {
        $service->assertAccessible($adjustment, $request->user(), 'delete');
        $service->delete($adjustment, $request->user());

        return response()->json(['message' => 'Stock adjustment removed and stock restored.']);
    }
}
