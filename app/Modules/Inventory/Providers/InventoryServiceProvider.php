<?php

namespace App\Modules\Inventory\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Inventory\Contracts\BatchServiceInterface;
use App\Modules\Inventory\Contracts\ProductServiceInterface;
use App\Modules\Inventory\Contracts\StockAdjustmentServiceInterface;
use App\Modules\Inventory\Contracts\StockMovementServiceInterface;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Policies\ProductPolicy;
use App\Modules\Inventory\Repositories\Interfaces\ProductRepositoryInterface;
use App\Modules\Inventory\Repositories\ProductRepository;
use App\Modules\Inventory\Services\BatchService;
use App\Modules\Inventory\Services\ProductService;
use App\Modules\Inventory\Services\StockAdjustmentService;
use App\Modules\Inventory\Services\StockMovementService;
use Illuminate\Support\Facades\Gate;

class InventoryServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(ProductServiceInterface::class, ProductService::class);
        $this->app->bind(BatchServiceInterface::class, BatchService::class);
        $this->app->bind(StockAdjustmentServiceInterface::class, StockAdjustmentService::class);
        $this->app->bind(StockMovementServiceInterface::class, StockMovementService::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
        Gate::policy(Product::class, ProductPolicy::class);
    }
}
