<?php

namespace App\Modules\Core\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Models\User;
use App\Modules\Core\Repositories\Interfaces\DashboardRepositoryInterface;

class DashboardService
{
    public function __construct(private readonly DashboardRepositoryInterface $dashboardRepository) {}

    public function summary(array $filters = [], ?User $user = null): array
    {
        return $this->dashboardRepository->summary($filters, $user);
    }
}
