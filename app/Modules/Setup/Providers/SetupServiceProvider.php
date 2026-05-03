<?php

namespace App\Modules\Setup\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;

class SetupServiceProvider extends BaseModuleServiceProvider
{
    public function register() {}

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
