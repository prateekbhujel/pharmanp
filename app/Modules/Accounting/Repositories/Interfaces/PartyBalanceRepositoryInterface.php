<?php

namespace App\Modules\Accounting\Repositories\Interfaces;

use App\Models\User;

interface PartyBalanceRepositoryInterface
{
    public function adjustSupplierBalance(int $supplierId, float $amount, ?User $user = null): void;

    public function adjustCustomerBalance(int $customerId, float $amount, ?User $user = null): void;
}
