<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Resources\StockMovementResource;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class StockMovementController extends Controller
{
    public function index(): JsonResponse
    {
        $sorts = [
            'movement_date' => 'movement_date',
            'movement_type' => 'movement_type',
            'quantity_in' => 'quantity_in',
            'quantity_out' => 'quantity_out',
            'created_at' => 'created_at',
        ];
        $sortField = $sorts[request('sort_field', 'movement_date')] ?? 'movement_date';
        $sortOrder = request('sort_order') === 'asc' ? 'asc' : 'desc';
        $search = trim((string) request('search'));

        $query = StockMovement::query()
            ->with(['product:id,name,sku,company_id', 'batch:id,batch_no,expires_at', 'creator:id,name'])
            ->when(request()->user()?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('movement_type', 'like', '%'.$search.'%')
                        ->orWhere('source_type', 'like', '%'.$search.'%')
                        ->orWhere('reference_type', 'like', '%'.$search.'%')
                        ->orWhere('notes', 'like', '%'.$search.'%')
                        ->orWhereHas('product', fn (Builder $product) => $product
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('sku', 'like', '%'.$search.'%'))
                        ->orWhereHas('batch', fn (Builder $batch) => $batch->where('batch_no', 'like', '%'.$search.'%'));
                });
            })
            ->when(request()->filled('product_id'), fn (Builder $builder) => $builder->where('product_id', request()->integer('product_id')))
            ->when(request()->filled('batch_id'), fn (Builder $builder) => $builder->where('batch_id', request()->integer('batch_id')))
            ->when(request()->filled('movement_type'), fn (Builder $builder) => $builder->where('movement_type', request('movement_type')))
            ->when(request()->filled('from'), fn (Builder $builder) => $builder->whereDate('movement_date', '>=', request('from')))
            ->when(request()->filled('to'), fn (Builder $builder) => $builder->whereDate('movement_date', '<=', request('to')))
            ->orderBy($sortField, $sortOrder)
            ->orderByDesc('id');

        $summaryQuery = clone $query;
        $movements = $query->paginate(min(100, max(5, request()->integer('per_page', 15))));

        return StockMovementResource::collection($movements)
            ->additional([
                'summary' => [
                    'total_rows' => (clone $summaryQuery)->count(),
                    'total_in' => (float) (clone $summaryQuery)->sum('quantity_in'),
                    'total_out' => (float) (clone $summaryQuery)->sum('quantity_out'),
                    'net' => (float) ((clone $summaryQuery)->sum('quantity_in') - (clone $summaryQuery)->sum('quantity_out')),
                ],
            ])
            ->response();
    }
}
