<?php

namespace App\Modules\Purchase\Repositories;

use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Models\PurchaseOrderItem;
use App\Modules\Purchase\Repositories\Interfaces\PurchaseOrderRepositoryInterface;

class PurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
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
