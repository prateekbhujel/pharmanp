<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\StockAdjustmentRequest;
use App\Modules\Inventory\Http\Resources\StockAdjustmentResource;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Contracts\StockAdjustmentServiceInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class StockAdjustmentController extends Controller
{
    public function index(): JsonResponse
    {
        $search = trim((string) request('search'));

        $query = StockAdjustment::query()
            ->with(['product:id,name', 'batch:id,batch_no,quantity_available', 'adjustedBy:id,name'])
            ->when(request()->user()?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('adjustment_type', 'like', '%'.$search.'%')
                        ->orWhere('reason', 'like', '%'.$search.'%')
                        ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('batch', fn (Builder $batch) => $batch->where('batch_no', 'like', '%'.$search.'%'));
                });
            })
            ->when(request()->filled('product_id'), fn (Builder $builder) => $builder->where('product_id', request()->integer('product_id')))
            ->when(request()->filled('batch_id'), fn (Builder $builder) => $builder->where('batch_id', request()->integer('batch_id')))
            ->when(request()->filled('adjustment_type'), fn (Builder $builder) => $builder->where('adjustment_type', request('adjustment_type')))
            ->when(request()->filled('from'), fn (Builder $builder) => $builder->whereDate('adjustment_date', '>=', request('from')))
            ->when(request()->filled('to'), fn (Builder $builder) => $builder->whereDate('adjustment_date', '<=', request('to')))
            ->orderByDesc('adjustment_date')
            ->orderByDesc('id')
            ->paginate(min(100, max(5, request()->integer('per_page', 15))));

        return StockAdjustmentResource::collection($query)->response();
    }

    public function store(StockAdjustmentRequest $request, StockAdjustmentServiceInterface $service): JsonResponse
    {
        $adjustment = $service->save($request->validated(), $request->user());

        return (new StockAdjustmentResource($adjustment))
            ->additional(['message' => 'Stock adjustment posted.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(StockAdjustmentRequest $request, StockAdjustment $adjustment, StockAdjustmentServiceInterface $service): StockAdjustmentResource
    {
        return new StockAdjustmentResource($service->save($request->validated(), $request->user(), $adjustment));
    }

    public function destroy(StockAdjustment $adjustment, StockAdjustmentServiceInterface $service): JsonResponse
    {
        $service->delete($adjustment, request()->user());

        return response()->json(['message' => 'Stock adjustment removed and stock restored.']);
    }
}
