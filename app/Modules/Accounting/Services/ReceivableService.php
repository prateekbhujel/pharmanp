<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Contracts\ReceivableServiceInterface;
use App\Modules\Party\Models\Customer;

class ReceivableService implements ReceivableServiceInterface
{
    public function adjustCustomerBalance(int $customerId, float $amount): void
    {
        $customer = Customer::query()->lockForUpdate()->find($customerId);

        if (! $customer) {
            return;
        }

        $customer->forceFill([
            'current_balance' => max(0, (float) $customer->current_balance + $amount),
        ])->save();
    }
}
