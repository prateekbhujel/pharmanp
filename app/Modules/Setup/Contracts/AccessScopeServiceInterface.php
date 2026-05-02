<?php

namespace App\Modules\Setup\Contracts;

use App\Models\User;

interface AccessScopeServiceInterface
{
    public function canAccessAll(User $user): bool;

    public function subordinateEmployeeIds(User $user): array;

    public function apply($query, User $user, array $columns = []);
}
