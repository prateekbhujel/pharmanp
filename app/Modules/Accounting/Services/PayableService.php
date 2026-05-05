<?php

namespace App\Modules\Accounting\Services;

use App\Models\User;
use App\Modules\Accounting\Repositories\Interfaces\PartyBalanceRepositoryInterface;

class PayableService
{
    public function __construct(
        private readonly PartyBalanceRepositoryInterface $balances,
    ) {}

    public function adjustSupplierBalance(int $supplierId, float $amount, ?User $user = null): void
    {
        $this->balances->adjustSupplierBalance($supplierId, $amount, $user);
    }
}
