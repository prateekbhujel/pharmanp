<?php

namespace App\Modules\MR\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;

class MrServiceProvider extends BaseModuleServiceProvider
{
    public function register() {}

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
