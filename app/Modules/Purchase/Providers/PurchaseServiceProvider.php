<?php

namespace App\Modules\Purchase\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Purchase\Repositories\Interfaces\PurchaseOrderRepositoryInterface;
use App\Modules\Purchase\Repositories\Interfaces\PurchaseRepositoryInterface;
use App\Modules\Purchase\Repositories\Interfaces\PurchaseReturnRepositoryInterface;
use App\Modules\Purchase\Repositories\PurchaseOrderRepository;
use App\Modules\Purchase\Repositories\PurchaseRepository;
use App\Modules\Purchase\Repositories\PurchaseReturnRepository;

class PurchaseServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(PurchaseRepositoryInterface::class, PurchaseRepository::class);
        $this->app->bind(PurchaseOrderRepositoryInterface::class, PurchaseOrderRepository::class);
        $this->app->bind(PurchaseReturnRepositoryInterface::class, PurchaseReturnRepository::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
