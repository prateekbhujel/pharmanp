<?php

namespace App\Modules\Purchase\Repositories\Interfaces;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Models\PurchaseOrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PurchaseOrderRepositoryInterface
{
    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator;

    public function create(array $data): PurchaseOrder;

    public function createItem(PurchaseOrder $order, array $data): PurchaseOrderItem;

    public function orderForReceive(int $id): PurchaseOrder;

    public function save(PurchaseOrder $order, array $data): PurchaseOrder;

    public function fresh(PurchaseOrder $order): PurchaseOrder;
}
