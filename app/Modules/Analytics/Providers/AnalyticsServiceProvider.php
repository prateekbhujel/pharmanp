<?php

namespace App\Modules\Analytics\Providers;

use App\Modules\Analytics\Repositories\Interfaces\InventorySignalRepositoryInterface;
use App\Modules\Analytics\Repositories\InventorySignalRepository;
use App\Modules\Base\Providers\BaseModuleServiceProvider;

class AnalyticsServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(InventorySignalRepositoryInterface::class, InventorySignalRepository::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
