<?php

namespace App\Modules\Inventory\Contracts;

use App\Models\User;
use App\Modules\Inventory\Models\Batch;

interface BatchServiceInterface
{
    public function save(array $data, User $user, ?Batch $batch = null): Batch;

    public function delete(Batch $batch, User $user): void;
}
