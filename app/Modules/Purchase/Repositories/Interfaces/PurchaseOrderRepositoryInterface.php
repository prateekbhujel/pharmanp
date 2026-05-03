<?php

namespace App\Modules\Purchase\Repositories\Interfaces;

use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Models\PurchaseOrderItem;

interface PurchaseOrderRepositoryInterface
{
    public function create(array $data): PurchaseOrder;

    public function createItem(PurchaseOrder $order, array $data): PurchaseOrderItem;

    public function orderForReceive(int $id): PurchaseOrder;

    public function save(PurchaseOrder $order, array $data): PurchaseOrder;

    public function fresh(PurchaseOrder $order): PurchaseOrder;
}
