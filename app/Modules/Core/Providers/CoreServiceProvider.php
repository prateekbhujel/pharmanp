<?php

namespace App\Modules\Core\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Core\Repositories\DashboardRepository;
use App\Modules\Core\Repositories\Interfaces\DashboardRepositoryInterface;

class CoreServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(DashboardRepositoryInterface::class, DashboardRepository::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
