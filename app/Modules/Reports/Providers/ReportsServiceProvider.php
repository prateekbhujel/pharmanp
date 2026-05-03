<?php

namespace App\Modules\Reports\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;

class ReportsServiceProvider extends BaseModuleServiceProvider
{
    public function register() {}

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
