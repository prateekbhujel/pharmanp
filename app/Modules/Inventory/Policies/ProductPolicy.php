<?php

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\Product;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_owner || $user->can('inventory.products.view');
    }

    public function create(User $user): bool
    {
        return $user->is_owner || $user->can('inventory.products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->is_owner || $user->can('inventory.products.update');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->is_owner || $user->can('inventory.products.delete');
    }
}
