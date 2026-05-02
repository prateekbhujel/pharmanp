<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Contracts\PayableServiceInterface;
use App\Modules\Party\Models\Supplier;

class PayableService implements PayableServiceInterface
{
    public function adjustSupplierBalance(int $supplierId, float $amount): void
    {
        $supplier = Supplier::query()->lockForUpdate()->find($supplierId);

        if (! $supplier) {
            return;
        }

        $supplier->forceFill([
            'current_balance' => max(0, (float) $supplier->current_balance + $amount),
        ])->save();
    }
}
