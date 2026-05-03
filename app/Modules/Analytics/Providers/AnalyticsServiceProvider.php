<?php

namespace App\Modules\Analytics\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;

class AnalyticsServiceProvider extends BaseModuleServiceProvider
{
    public function register() {}

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
