<?php

namespace App\Modules\Accounting\Services;

use App\Models\User;
use App\Modules\Accounting\Repositories\Interfaces\PartyBalanceRepositoryInterface;

class ReceivableService
{
    public function __construct(
        private readonly PartyBalanceRepositoryInterface $balances,
    ) {}

    public function adjustCustomerBalance(int $customerId, float $amount, ?User $user = null): void
    {
        $this->balances->adjustCustomerBalance($customerId, $amount, $user);
    }
}
