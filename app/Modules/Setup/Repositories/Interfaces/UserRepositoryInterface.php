<?php

namespace App\Modules\Setup\Repositories\Interfaces;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function paginate(TableQueryData $table, ?User $actor = null): LengthAwarePaginator;

    public function create(array $payload): User;

    public function update(User $user, array $payload): User;

    public function syncRoles(User $user, array $roles): void;

    public function detachRoles(User $user): void;

    public function fresh(User $user): User;

    public function delete(User $user): void;
}
