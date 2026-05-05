<?php

namespace App\Modules\Inventory\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Policies\BatchPolicy;
use App\Modules\Inventory\Policies\ProductPolicy;
use App\Modules\Inventory\Repositories\Interfaces\InventoryMasterRepositoryInterface;
use App\Modules\Inventory\Repositories\Interfaces\ProductRepositoryInterface;
use App\Modules\Inventory\Repositories\InventoryMasterRepository;
use App\Modules\Inventory\Repositories\ProductRepository;
use Illuminate\Support\Facades\Gate;

class InventoryServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(InventoryMasterRepositoryInterface::class, InventoryMasterRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
        Gate::policy(Batch::class, BatchPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
    }
}
