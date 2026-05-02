<?php

namespace App\Modules\Accounting\Contracts;

interface PayableServiceInterface
{
    public function adjustSupplierBalance(int $supplierId, float $amount): void;
}
