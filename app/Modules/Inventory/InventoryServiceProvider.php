<?php

namespace App\Modules\Inventory;

use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Policies\ProductPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);
    }
}
