<?php

namespace App\Modules\MR\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\MR\Repositories\Interfaces\MrRepositoryInterface;
use App\Modules\MR\Repositories\MrRepository;

class MrServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(MrRepositoryInterface::class, MrRepository::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
