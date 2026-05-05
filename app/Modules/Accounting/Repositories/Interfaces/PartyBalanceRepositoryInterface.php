<?php

namespace App\Modules\Accounting\Repositories\Interfaces;

interface PartyBalanceRepositoryInterface
{
    public function adjustSupplierBalance(int $supplierId, float $amount): void;

    public function adjustCustomerBalance(int $customerId, float $amount): void;
}
