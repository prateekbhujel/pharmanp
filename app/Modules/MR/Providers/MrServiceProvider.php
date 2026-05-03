<?php

namespace App\Modules\MR\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\MR\Contracts\MrManagementServiceInterface;
use App\Modules\MR\Contracts\MrPerformanceServiceInterface;
use App\Modules\MR\Services\MrManagementService;
use App\Modules\MR\Services\MrPerformanceService;

class MrServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(MrManagementServiceInterface::class, MrManagementService::class);
        $this->app->bind(MrPerformanceServiceInterface::class, MrPerformanceService::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
