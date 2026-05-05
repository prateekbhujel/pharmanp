<?php

namespace App\Modules\Core\Providers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Core\Repositories\DashboardRepository;
use App\Modules\Core\Repositories\GlobalSearchRepository;
use App\Modules\Core\Repositories\Interfaces\DashboardRepositoryInterface;
use App\Modules\Core\Repositories\Interfaces\GlobalSearchRepositoryInterface;

class CoreServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(DashboardRepositoryInterface::class, DashboardRepository::class);
        $this->app->bind(GlobalSearchRepositoryInterface::class, GlobalSearchRepository::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
