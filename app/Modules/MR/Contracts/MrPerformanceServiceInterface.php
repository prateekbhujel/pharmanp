<?php

namespace App\Modules\MR\Contracts;

use App\Models\User;

interface MrPerformanceServiceInterface
{
    public function monthly(?User $user = null, array $filters = []): array;
}
