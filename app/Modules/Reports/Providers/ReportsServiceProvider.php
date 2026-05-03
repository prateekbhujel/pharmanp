<?php

namespace App\Modules\Reports\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Reports\Repositories\Interfaces\ReportRepositoryInterface;
use App\Modules\Reports\Repositories\ReportRepository;

class ReportsServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(ReportRepositoryInterface::class, ReportRepository::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
