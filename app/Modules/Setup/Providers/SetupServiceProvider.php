<?php

namespace App\Modules\Setup\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Setup\Repositories\DropdownOptionRepository;
use App\Modules\Setup\Repositories\FiscalYearRepository;
use App\Modules\Setup\Repositories\Interfaces\DropdownOptionRepositoryInterface;
use App\Modules\Setup\Repositories\Interfaces\FiscalYearRepositoryInterface;
use App\Modules\Setup\Repositories\Interfaces\SettingsRepositoryInterface;
use App\Modules\Setup\Repositories\Interfaces\SetupTypeRepositoryInterface;
use App\Modules\Setup\Repositories\Interfaces\UserRepositoryInterface;
use App\Modules\Setup\Repositories\SettingsRepository;
use App\Modules\Setup\Repositories\SetupTypeRepository;
use App\Modules\Setup\Repositories\UserRepository;

class SetupServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(DropdownOptionRepositoryInterface::class, DropdownOptionRepository::class);
        $this->app->bind(FiscalYearRepositoryInterface::class, FiscalYearRepository::class);
        $this->app->bind(SettingsRepositoryInterface::class, SettingsRepository::class);
        $this->app->bind(SetupTypeRepositoryInterface::class, SetupTypeRepository::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
