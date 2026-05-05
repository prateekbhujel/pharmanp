<?php

namespace App\Modules\Core\Services;

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
