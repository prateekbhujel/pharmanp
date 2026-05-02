<?php

namespace App\Modules\Inventory\Contracts;

use App\Models\User;
use App\Modules\Inventory\Models\StockAdjustment;

interface StockAdjustmentServiceInterface
{
    public function save(array $data, User $user, ?StockAdjustment $adjustment = null): StockAdjustment;

    public function delete(StockAdjustment $adjustment, User $user): void;
}
