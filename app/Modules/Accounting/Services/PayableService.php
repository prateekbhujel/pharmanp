<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Contracts\PayableServiceInterface;
use App\Modules\Accounting\Repositories\Interfaces\PartyBalanceRepositoryInterface;

class PayableService implements PayableServiceInterface
{
    public function __construct(
        private readonly PartyBalanceRepositoryInterface $balances,
    ) {}

    public function adjustSupplierBalance(int $supplierId, float $amount): void
    {
        $this->balances->adjustSupplierBalance($supplierId, $amount);
    }
}
