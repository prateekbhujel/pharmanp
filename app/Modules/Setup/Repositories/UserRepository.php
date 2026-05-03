<?php

namespace App\Modules\Setup\Repositories;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Setup\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class UserRepository implements UserRepositoryInterface
{
    private const SORTS = [
        'name' => 'users.name',
        'email' => 'users.email',
        'is_active' => 'users.is_active',
        'last_login_at' => 'users.last_login_at',
        'created_at' => 'users.created_at',
        'updated_at' => 'users.updated_at',
    ];

    public function paginate(TableQueryData $table, ?User $actor = null): LengthAwarePaginator
    {
        $query = User::query()
            ->with(['roles:id,name', 'branch:id,name,code,type', 'medicalRepresentative:id,name'])
            ->when($actor?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('users.tenant_id', $tenantId))
            ->when($table->search, function (Builder $builder, string $search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('users.name', 'like', '%'.$search.'%')
                        ->orWhere('users.email', 'like', '%'.$search.'%')
                        ->orWhere('users.phone', 'like', '%'.$search.'%')
                        ->orWhereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when(array_key_exists('is_active', $table->filters), fn (Builder $builder) => $builder->where('users.is_active', (bool) $table->filters['is_active']))
            ->when(array_key_exists('role_name', $table->filters), fn (Builder $builder) => $builder->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', $table->filters['role_name'])));

        $sort = self::SORTS[$table->sortField] ?? self::SORTS['updated_at'];

        return $query->orderBy($sort, $table->sortOrder)
            ->paginate($table->perPage, ['*'], 'page', $table->page);
    }

    public function create(array $payload): User
    {
        return User::query()->create($payload);
    }

    public function update(User $user, array $payload): User
    {
        $user->update($payload);

        return $user;
    }

    public function syncRoles(User $user, array $roles): void
    {
        $user->syncRoles($roles);
    }

    public function detachRoles(User $user): void
    {
        $user->roles()->detach();
    }

    public function fresh(User $user): User
    {
        return $user->fresh(['roles:id,name', 'branch:id,name,code,type', 'medicalRepresentative:id,name']);
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
