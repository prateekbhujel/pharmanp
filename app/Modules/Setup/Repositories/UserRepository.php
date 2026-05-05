<?php

namespace App\Modules\Setup\Repositories;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Core\Query\TableQueryApplier;
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

    public function __construct(private readonly TableQueryApplier $tables) {}

    public function paginate(TableQueryData $table, ?User $actor = null): LengthAwarePaginator
    {
        $query = User::query()
            ->with(['roles:id,name', 'branch:id,name,code,type', 'medicalRepresentative:id,name'])
            ->when(array_key_exists('role_name', $table->filters), fn (Builder $builder) => $builder->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', $table->filters['role_name'])));

        $this->tables->tenant($query, $actor, 'users.tenant_id');
        $query->when($table->search, function (Builder $builder, string $search): void {
            $builder->where(function (Builder $inner) use ($search): void {
                $this->tables->search($inner, $search, ['users.name', 'users.email', 'users.phone']);
                $inner->orWhereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', 'like', '%'.$search.'%'));
            });
        });
        $this->tables->activeFilter($query, $table, 'users.is_active');

        return $this->tables->paginate(
            $query->orderBy($this->tables->sortColumn($table, self::SORTS, 'updated_at'), $table->sortOrder),
            $table,
        );
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
