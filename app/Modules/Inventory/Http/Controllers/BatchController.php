<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\BatchRequest;
use App\Modules\Inventory\Http\Resources\BatchResource;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Contracts\BatchServiceInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class BatchController extends Controller
{
    public function index(): JsonResponse
    {
        $sorts = [
            'batch_no' => 'batch_no',
            'expires_at' => 'expires_at',
            'quantity_available' => 'quantity_available',
            'purchase_price' => 'purchase_price',
            'mrp' => 'mrp',
            'created_at' => 'created_at',
        ];
        $sortField = $sorts[request('sort_field', 'expires_at')] ?? 'expires_at';
        $sortOrder = request('sort_order') === 'desc' ? 'desc' : 'asc';
        $search = trim((string) request('search'));

        $query = Batch::query()
            ->with(['product.company', 'supplier'])
            ->when(request()->user()?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('batch_no', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%')
                        ->orWhere('storage_location', 'like', '%'.$search.'%')
                        ->orWhereHas('product', fn (Builder $product) => $product
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('generic_name', 'like', '%'.$search.'%')
                            ->orWhere('sku', 'like', '%'.$search.'%'))
                        ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when(request()->filled('product_id'), fn (Builder $builder) => $builder->where('product_id', request()->integer('product_id')))
            ->when(request()->filled('supplier_id'), fn (Builder $builder) => $builder->where('supplier_id', request()->integer('supplier_id')))
            ->when(request()->filled('expiry_status'), fn (Builder $builder) => $this->expiryFilter($builder, request('expiry_status')))
            ->when(request()->filled('from'), fn (Builder $builder) => $builder->whereDate('expires_at', '>=', request('from')))
            ->when(request()->filled('to'), fn (Builder $builder) => $builder->whereDate('expires_at', '<=', request('to')))
            ->when(request()->filled('is_active'), fn (Builder $builder) => $builder->where('is_active', request()->boolean('is_active')))
            ->orderBy($sortField, $sortOrder)
            ->orderBy('id');

        $summaryQuery = clone $query;
        $batches = $query->paginate(min(100, max(5, request()->integer('per_page', 15))));

        return BatchResource::collection($batches)
            ->additional([
                'summary' => [
                    'total_batches' => (clone $summaryQuery)->count(),
                    'total_stock' => (float) (clone $summaryQuery)->sum('quantity_available'),
                    'expired_batches' => (clone $summaryQuery)->whereDate('expires_at', '<', today())->count(),
                    'expiring_30' => (clone $summaryQuery)->whereBetween('expires_at', [today(), today()->addDays(30)])->count(),
                ],
            ])
            ->response();
    }

    public function options(): JsonResponse
    {
        $batches = Batch::query()
            ->with('product:id,name')
            ->when(request()->user()?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->where('quantity_available', '>', 0)
            ->when(request()->filled('product_id'), fn (Builder $builder) => $builder->where('product_id', request()->integer('product_id')))
            ->when(request()->filled('supplier_id'), fn (Builder $builder) => $builder->where('supplier_id', request()->integer('supplier_id')))
            ->orderBy('expires_at')
            ->orderBy('batch_no')
            ->limit(100)
            ->get();

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

    public function store(BatchRequest $request, BatchServiceInterface $service): JsonResponse
    {
        $batch = $service->save($request->validated(), $request->user());

        return (new BatchResource($batch))
            ->additional(['message' => 'Batch saved.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(BatchRequest $request, Batch $batch, BatchServiceInterface $service): BatchResource
    {
        return new BatchResource($service->save($request->validated(), $request->user(), $batch));
    }

    public function destroy(Batch $batch, BatchServiceInterface $service): JsonResponse
    {
        $service->delete($batch, request()->user());

        return response()->json(['message' => 'Batch removed.']);
    }

    private function expiryFilter(Builder $builder, string $status): void
    {
        if ($status === 'expired') {
            $builder->whereDate('expires_at', '<', today());
        } elseif ($status === '30d') {
            $builder->whereBetween('expires_at', [today(), today()->addDays(30)]);
        } elseif ($status === '60d') {
            $builder->whereBetween('expires_at', [today(), today()->addDays(60)]);
        } elseif ($status === 'available') {
            $builder->where('quantity_available', '>', 0);
        }
    }
}
