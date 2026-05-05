<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Repositories\Interfaces\PartyBalanceRepositoryInterface;

class ReceivableService
{
    public function __construct(
        private readonly PartyBalanceRepositoryInterface $balances,
    ) {}

    public function adjustCustomerBalance(int $customerId, float $amount): void
    {
        $this->balances->adjustCustomerBalance($customerId, $amount);
    }
}
