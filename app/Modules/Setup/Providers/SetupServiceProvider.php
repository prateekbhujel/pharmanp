<?php

namespace App\Modules\Setup\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Setup\Repositories\Interfaces\UserRepositoryInterface;
use App\Modules\Setup\Repositories\UserRepository;

class SetupServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
