<?php

namespace App\Modules\Accounting\Repositories\Interfaces;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

interface PartyBalanceRepositoryInterface
{
    public function adjustSupplierBalance(int $supplierId, float $amount): void;

    public function adjustCustomerBalance(int $customerId, float $amount): void;
}
