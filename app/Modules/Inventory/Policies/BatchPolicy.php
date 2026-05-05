<?php

namespace App\Modules\Inventory\Policies;

use App\Core\Security\TenantRecordScope;
use App\Models\User;
use App\Modules\Inventory\Models\Batch;

class BatchPolicy
{
    public function __construct(private readonly TenantRecordScope $records) {}

    public function viewAny(User $user): bool
    {
        return $user->is_owner || $user->can('inventory.batches.view');
    }

    public function view(User $user, Batch $batch): bool
    {
        return ($user->is_owner || $user->can('inventory.batches.view'))
            && $this->records->canAccess($user, $batch);
    }

    public function create(User $user): bool
    {
        return $user->is_owner || $user->can('inventory.batches.create');
    }

    public function update(User $user, Batch $batch): bool
    {
        return ($user->is_owner || $user->can('inventory.batches.update'))
            && $this->records->canAccess($user, $batch);
    }

    public function delete(User $user, Batch $batch): bool
    {
        return ($user->is_owner || $user->can('inventory.batches.delete'))
            && $this->records->canAccess($user, $batch);
    }
}
