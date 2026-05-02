<?php

namespace App\Modules\Purchase\Contracts;

use App\Models\User;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseOrder;

interface PurchaseOrderServiceInterface
{
    public function create(array $data, User $user): PurchaseOrder;

    public function receive(PurchaseOrder $order, array $data, User $user, PurchaseEntryServiceInterface $purchases): Purchase;
}
