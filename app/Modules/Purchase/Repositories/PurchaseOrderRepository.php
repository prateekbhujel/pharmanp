<?php

namespace App\Modules\Purchase\Repositories;

use App\Core\DTOs\TableQueryData;
use App\Core\Query\TableQueryApplier;
use App\Models\User;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Models\PurchaseOrderItem;
use App\Modules\Purchase\Repositories\Interfaces\PurchaseOrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    private const SORTS = [
        'order_date' => 'order_date',
        'order_no' => 'order_no',
        'grand_total' => 'grand_total',
        'status' => 'status',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function __construct(private readonly TableQueryApplier $tables) {}

    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()
            ->with('supplier:id,name');

        $this->tables->tenant($query, $user, 'tenant_id');

        $query
            ->when($table->search, function (Builder $builder, string $search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $this->tables->search($inner, $search, ['order_no', 'notes']);
                    $inner->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($table->filters['supplier_id'] ?? null, fn (Builder $builder, mixed $supplierId) => $builder->where('supplier_id', $supplierId))
            ->when($table->filters['status'] ?? null, fn (Builder $builder, mixed $status) => $builder->where('status', $status))
            ->when($table->filters['from'] ?? null, fn (Builder $builder, mixed $from) => $builder->whereDate('order_date', '>=', $from))
            ->when($table->filters['to'] ?? null, fn (Builder $builder, mixed $to) => $builder->whereDate('order_date', '<=', $to));

        return $this->tables->paginate(
            $query
                ->orderBy($this->tables->sortColumn($table, self::SORTS, 'order_date'), $table->sortOrder)
                ->orderByDesc('id'),
            $table,
        );
    }

    public function create(array $data): PurchaseOrder
    {
        return PurchaseOrder::query()->create($data);
    }

    public function createItem(PurchaseOrder $order, array $data): PurchaseOrderItem
    {
        return $order->items()->create($data);
    }

    public function orderForReceive(int $id): PurchaseOrder
    {
        return PurchaseOrder::query()
            ->with('items')
            ->lockForUpdate()
            ->findOrFail($id);
    }

    public function save(PurchaseOrder $order, array $data): PurchaseOrder
    {
        $order->forceFill($data)->save();

        return $order;
    }

    public function fresh(PurchaseOrder $order): PurchaseOrder
    {
        return $order->fresh(['supplier', 'items.product']);
    }
}
