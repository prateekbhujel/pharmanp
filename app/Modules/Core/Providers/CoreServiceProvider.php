<?php

namespace App\Modules\Core\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;

class CoreServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
