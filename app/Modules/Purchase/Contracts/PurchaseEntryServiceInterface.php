<?php

namespace App\Modules\Purchase\Contracts;

use App\Models\User;
use App\Modules\Purchase\Models\Purchase;

interface PurchaseEntryServiceInterface
{
    public function create(array $data, User $user): Purchase;
}
