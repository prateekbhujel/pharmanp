<?php

namespace App\Modules\Setup\Contracts;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserManagementServiceInterface
{
    public function paginate(TableQueryData $table, ?User $actor = null): LengthAwarePaginator;

    public function create(array $data, User $actor): User;

    public function update(User $user, array $data, User $actor): User;

    public function toggleStatus(User $user, bool $active, User $actor): User;

    public function delete(User $user, User $actor): void;

    public function updateProfile(User $user, array $data): User;
}
