<?php

namespace App\Modules\Core\Repositories\Interfaces;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Models\User;

interface DashboardRepositoryInterface
{
    public function summary(array $filters = [], ?User $user = null): array;
}
