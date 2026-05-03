<?php

namespace App\Modules\MR\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\MR\Contracts\MrManagementServiceInterface;
use App\Modules\MR\Contracts\MrPerformanceServiceInterface;
use App\Modules\MR\Services\MrManagementService;
use App\Modules\MR\Services\MrPerformanceService;

class MrServiceProvider extends BaseModuleServiceProvider
{
    protected function bindings(): array
    {
        return [
            MrManagementServiceInterface::class => MrManagementService::class,
            MrPerformanceServiceInterface::class => MrPerformanceService::class,
        ];
    }
}
