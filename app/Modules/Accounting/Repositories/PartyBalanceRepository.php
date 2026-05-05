<?php

namespace App\Modules\Accounting\Repositories;

use App\Modules\Accounting\Repositories\Interfaces\PartyBalanceRepositoryInterface;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;

class PartyBalanceRepository implements PartyBalanceRepositoryInterface
{
    public function adjustSupplierBalance(int $supplierId, float $amount): void
    {
        $supplier = Supplier::query()->lockForUpdate()->find($supplierId);

        if ($supplier) {
            $supplier->forceFill([
                'current_balance' => max(0, (float) $supplier->current_balance + $amount),
            ])->save();
        }
    }

    public function adjustCustomerBalance(int $customerId, float $amount): void
    {
        $customer = Customer::query()->lockForUpdate()->find($customerId);

        if ($customer) {
            $customer->forceFill([
                'current_balance' => max(0, (float) $customer->current_balance + $amount),
            ])->save();
        }
    }
}
