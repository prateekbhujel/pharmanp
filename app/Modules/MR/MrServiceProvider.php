<?php

namespace App\Modules\MR;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\MR\Contracts\MrManagementServiceInterface;
use App\Modules\MR\Contracts\MrPerformanceServiceInterface;
use App\Modules\MR\Services\MrManagementService;
use App\Modules\MR\Services\MrPerformanceService;

class MrServiceProvider extends ModuleServiceProvider
{
    protected function bindings(): array
    {
        return [
            MrManagementServiceInterface::class => MrManagementService::class,
            MrPerformanceServiceInterface::class => MrPerformanceService::class,
        ];
    }
}
