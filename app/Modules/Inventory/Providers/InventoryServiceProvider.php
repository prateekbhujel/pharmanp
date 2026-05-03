<?php

namespace App\Modules\Inventory\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Policies\ProductPolicy;
use App\Modules\Inventory\Repositories\Interfaces\ProductRepositoryInterface;
use App\Modules\Inventory\Repositories\ProductRepository;
use Illuminate\Support\Facades\Gate;

class InventoryServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
        Gate::policy(Product::class, ProductPolicy::class);
    }
}
