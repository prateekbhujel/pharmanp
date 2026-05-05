<?php

namespace App\Modules\Inventory\Policies;

use App\Core\Security\TenantRecordScope;
use App\Models\User;
use App\Modules\Inventory\Models\Product;

class ProductPolicy
{
    public function __construct(private readonly TenantRecordScope $records) {}

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
        return ($user->is_owner || $user->can('inventory.products.update'))
            && $this->records->canAccess($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return ($user->is_owner || $user->can('inventory.products.delete'))
            && $this->records->canAccess($user, $product);
    }
}
