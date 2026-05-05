<?php

namespace App\Modules\Analytics\Repositories\Interfaces;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Analytics\DTOs\InventorySignalFilterData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

interface InventorySignalRepositoryInterface
{
    public function rows(InventorySignalFilterData $filters, CarbonImmutable $today): Collection;
}
