<?php

namespace App\Modules\Accounting\Contracts;

interface ReceivableServiceInterface
{
    public function adjustCustomerBalance(int $customerId, float $amount): void;
}
