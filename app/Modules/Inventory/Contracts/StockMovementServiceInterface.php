<?php

namespace App\Modules\Inventory\Contracts;

use App\Modules\Inventory\Models\StockMovement;

interface StockMovementServiceInterface
{
    public function record(array $data): StockMovement;
}
