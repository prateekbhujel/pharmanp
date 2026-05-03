<?php

namespace App\Modules\Purchase\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Purchase\Contracts\PurchaseEntryServiceInterface;
use App\Modules\Purchase\Contracts\PurchaseOrderServiceInterface;
use App\Modules\Purchase\Contracts\PurchaseReturnServiceInterface;
use App\Modules\Purchase\Repositories\Interfaces\PurchaseOrderRepositoryInterface;
use App\Modules\Purchase\Repositories\Interfaces\PurchaseRepositoryInterface;
use App\Modules\Purchase\Repositories\Interfaces\PurchaseReturnRepositoryInterface;
use App\Modules\Purchase\Repositories\PurchaseOrderRepository;
use App\Modules\Purchase\Repositories\PurchaseRepository;
use App\Modules\Purchase\Repositories\PurchaseReturnRepository;
use App\Modules\Purchase\Services\PurchaseEntryService;
use App\Modules\Purchase\Services\PurchaseOrderService;
use App\Modules\Purchase\Services\PurchaseReturnService;

class PurchaseServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(PurchaseRepositoryInterface::class, PurchaseRepository::class);
        $this->app->bind(PurchaseOrderRepositoryInterface::class, PurchaseOrderRepository::class);
        $this->app->bind(PurchaseReturnRepositoryInterface::class, PurchaseReturnRepository::class);
        $this->app->bind(PurchaseEntryServiceInterface::class, PurchaseEntryService::class);
        $this->app->bind(PurchaseOrderServiceInterface::class, PurchaseOrderService::class);
        $this->app->bind(PurchaseReturnServiceInterface::class, PurchaseReturnService::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
