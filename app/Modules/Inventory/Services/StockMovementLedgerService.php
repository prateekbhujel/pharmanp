<?php

namespace App\Modules\Inventory\Services;

use App\Core\Query\TableQueryApplier;
use App\Models\User;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class StockMovementLedgerService
{
    private const SORTS = [
        'movement_date' => 'movement_date',
        'movement_type' => 'movement_type',
        'quantity_in' => 'quantity_in',
        'quantity_out' => 'quantity_out',
        'created_at' => 'created_at',
    ];

    public function __construct(private readonly TableQueryApplier $tables) {}

    public function table(Request $request, ?User $user): array
    {
        $sortField = self::SORTS[$request->query('sort_field', 'movement_date')] ?? 'movement_date';
        $sortOrder = $request->query('sort_order') === 'asc' ? 'asc' : 'desc';
        $search = trim((string) $request->query('search'));

        $query = StockMovement::query()
            ->with(['product:id,name,sku,company_id', 'batch:id,batch_no,expires_at', 'creator:id,name'])
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
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
            ->when($request->filled('product_id'), fn (Builder $builder) => $builder->where('product_id', $request->integer('product_id')))
            ->when($request->filled('batch_id'), fn (Builder $builder) => $builder->where('batch_id', $request->integer('batch_id')))
            ->when($request->filled('movement_type'), fn (Builder $builder) => $builder->where('movement_type', $request->query('movement_type')))
            ->when($request->filled('from'), fn (Builder $builder) => $builder->whereDate('movement_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn (Builder $builder) => $builder->whereDate('movement_date', '<=', $request->query('to')))
            ->orderBy($sortField, $sortOrder)
            ->orderByDesc('id');

        $this->tables->operatingContext($query, $user);

        $summaryQuery = clone $query;

        return [
            'movements' => $query->paginate(min(100, max(5, $request->integer('per_page', 15)))),
            'summary' => [
                'total_rows' => (clone $summaryQuery)->count(),
                'total_in' => (float) (clone $summaryQuery)->sum('quantity_in'),
                'total_out' => (float) (clone $summaryQuery)->sum('quantity_out'),
                'net' => (float) ((clone $summaryQuery)->sum('quantity_in') - (clone $summaryQuery)->sum('quantity_out')),
            ],
        ];
    }
}
