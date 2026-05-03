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
    protected function bindings(): array
    {
        return [
            ProductRepositoryInterface::class => ProductRepository::class,
            ProductServiceInterface::class => ProductService::class,
            BatchServiceInterface::class => BatchService::class,
            StockAdjustmentServiceInterface::class => StockAdjustmentService::class,
            StockMovementServiceInterface::class => StockMovementService::class,
        ];
    }

    protected function bootModule(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);
    }
}
