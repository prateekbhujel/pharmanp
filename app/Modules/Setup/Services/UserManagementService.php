<?php

namespace App\Modules\Setup\Services;

use App\Core\DTOs\TableQueryData;
use App\Core\Support\WorkspaceScope;
use App\Models\User;
use App\Modules\MR\Models\MedicalRepresentative;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    private const SORTS = [
        'name' => 'users.name',
        'email' => 'users.email',
        'is_active' => 'users.is_active',
        'last_login_at' => 'users.last_login_at',
        'created_at' => 'users.created_at',
    ];

    public function paginate(TableQueryData $table, ?User $actor = null): LengthAwarePaginator
    {
        $query = User::query()
            ->with(['roles:id,name', 'medicalRepresentative:id,name'])
            ->when($table->search, function (Builder $builder, string $search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('users.name', 'like', '%'.$search.'%')
                        ->orWhere('users.email', 'like', '%'.$search.'%')
                        ->orWhere('users.phone', 'like', '%'.$search.'%')
                        ->orWhereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when(array_key_exists('is_active', $table->filters), fn (Builder $builder) => $builder->where('users.is_active', (bool) $table->filters['is_active']))
            ->when(array_key_exists('is_owner', $table->filters), fn (Builder $builder) => $builder->where('users.is_owner', (bool) $table->filters['is_owner']))
            ->when(array_key_exists('medical_representative_linked', $table->filters), function (Builder $builder) use ($table) {
                if ((bool) $table->filters['medical_representative_linked']) {
                    $builder->whereNotNull('users.medical_representative_id');
                    return;
                }

                $builder->whereNull('users.medical_representative_id');
            })
            ->when(array_key_exists('role_name', $table->filters), fn (Builder $builder) => $builder->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', $table->filters['role_name'])));

        WorkspaceScope::apply($query, $actor, 'users', ['tenant_id', 'company_id', 'store_id']);

        $sort = self::SORTS[$table->sortField] ?? self::SORTS['created_at'];

        return $query->orderBy($sort, $table->sortOrder)
            ->paginate($table->perPage, ['*'], 'page', $table->page);
    }

    public function summary(User $actor): array
    {
        $query = User::query();
        WorkspaceScope::apply($query, $actor, 'users', ['tenant_id', 'company_id', 'store_id']);

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('is_active', true)->count(),
            'inactive' => (clone $query)->where('is_active', false)->count(),
            'owners' => (clone $query)->where('is_owner', true)->count(),
            'mr_linked' => (clone $query)->whereNotNull('medical_representative_id')->count(),
        ];
    }

    public function create(array $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor) {
            $medicalRepresentativeId = $this->resolveMedicalRepresentativeId($data['medical_representative_id'] ?? null, $actor);

            $user = User::query()->create([
                'tenant_id' => $actor->tenant_id,
                'company_id' => $actor->company_id,
                'store_id' => $actor->store_id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'medical_representative_id' => $medicalRepresentativeId,
                'is_owner' => (bool) ($data['is_owner'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $user->syncRoles($data['role_names']);

            return $user->load(['roles:id,name', 'medicalRepresentative:id,name']);
        });
    }

    public function update(User $user, array $data, User $actor): User
    {
        WorkspaceScope::ensure($user, $actor, ['tenant_id', 'company_id', 'store_id']);

        return DB::transaction(function () use ($user, $data, $actor) {
            $medicalRepresentativeId = $this->resolveMedicalRepresentativeId($data['medical_representative_id'] ?? null, $actor);

            if ($actor->id === $user->id && array_key_exists('is_active', $data) && ! $data['is_active']) {
                throw ValidationException::withMessages([
                    'is_active' => 'You cannot deactivate your own account.',
                ]);
            }

            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'medical_representative_id' => $medicalRepresentativeId,
                'is_active' => (bool) ($data['is_active'] ?? $user->is_active),
                'is_owner' => (bool) ($data['is_owner'] ?? $user->is_owner),
            ];

            if (! empty($data['password'])) {
                $payload['password'] = Hash::make($data['password']);
            }

            $user->update($payload);
            $user->syncRoles($data['role_names']);

            return $user->fresh(['roles:id,name', 'medicalRepresentative:id,name']);
        });
    }

    public function delete(User $user, User $actor): void
    {
        WorkspaceScope::ensure($user, $actor, ['tenant_id', 'company_id', 'store_id']);

        if ($actor->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        DB::transaction(function () use ($user) {
            $user->roles()->detach();
            $user->delete();
        });
    }

    public function updateProfile(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ];

            if (! empty($data['password'])) {
                if (empty($data['current_password']) || ! Hash::check($data['current_password'], $user->password)) {
                    throw ValidationException::withMessages([
                        'current_password' => 'Current password did not match.',
                    ]);
                }

                $payload['password'] = Hash::make($data['password']);
            }

            $user->update($payload);

            return $user->fresh(['roles:id,name', 'medicalRepresentative:id,name']);
        });
    }

    private function resolveMedicalRepresentativeId(?int $medicalRepresentativeId, User $actor): ?int
    {
        if (! $medicalRepresentativeId) {
            return null;
        }

        $representative = MedicalRepresentative::query()->find($medicalRepresentativeId);

        if (! $representative) {
            throw ValidationException::withMessages([
                'medical_representative_id' => 'The selected medical representative could not be found.',
            ]);
        }

        WorkspaceScope::ensure($representative, $actor, ['tenant_id', 'company_id']);

        return $representative->id;
    }
}
