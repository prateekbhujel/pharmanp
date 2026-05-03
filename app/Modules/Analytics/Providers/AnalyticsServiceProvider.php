<?php

namespace App\Modules\Analytics\Providers;

use App\Modules\Analytics\Contracts\PharmaSignalServiceInterface;
use App\Modules\Analytics\Services\PharmaSignalService;
use App\Modules\Base\Providers\BaseModuleServiceProvider;

class AnalyticsServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(PharmaSignalServiceInterface::class, PharmaSignalService::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
