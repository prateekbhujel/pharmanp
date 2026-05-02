<?php

namespace App\Modules\Purchase\Contracts;

use App\Models\User;
use App\Modules\Purchase\Models\PurchaseReturn;

interface PurchaseReturnServiceInterface
{
    public function save(array $data, User $user, ?PurchaseReturn $purchaseReturn = null): PurchaseReturn;

    public function delete(PurchaseReturn $purchaseReturn, User $user): void;
}
