<?php

namespace App\Modules\Setup\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Setup\DTOs\UserData;
use App\Modules\Setup\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function paginate(TableQueryData $table, ?User $actor = null): LengthAwarePaginator
    {
        return $this->users->paginate($table, $actor);
    }

    public function create(array $data, User $actor): User
    {
        $dto = UserData::fromArray($data);

        return DB::transaction(function () use ($dto, $actor) {
            $user = $this->users->create([
                'tenant_id' => $actor->tenant_id,
                'company_id' => $actor->company_id,
                'store_id' => $actor->store_id,
                'branch_id' => $dto->branchId ?? $actor->branch_id,
                'name' => $dto->name,
                'email' => $dto->email,
                'phone' => $dto->phone,
                'password' => Hash::make((string) $dto->password),
                'medical_representative_id' => $dto->medicalRepresentativeId,
                'is_owner' => $dto->isOwner,
                'is_active' => $dto->isActive,
            ]);

            $this->users->syncRoles($user, $dto->roleNames);

            return $this->users->fresh($user);
        });
    }

    public function update(User $user, array $data, User $actor): User
    {
        $dto = UserData::fromArray([
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'is_owner' => $user->is_owner,
            'role_names' => $user->roles->pluck('name')->all(),
            ...$data,
        ]);

        return DB::transaction(function () use ($user, $dto, $data, $actor) {
            if ($actor->id === $user->id && array_key_exists('is_active', $data) && ! $dto->isActive) {
                throw ValidationException::withMessages([
                    'is_active' => 'You cannot deactivate your own account.',
                ]);
            }

            $payload = [
                'name' => $dto->name,
                'email' => $dto->email,
                'phone' => $dto->phone,
                'branch_id' => $dto->branchId ?? $user->branch_id,
                'medical_representative_id' => $dto->medicalRepresentativeId,
                'is_active' => $dto->isActive,
                'is_owner' => $dto->isOwner,
            ];

            if ($dto->password) {
                $payload['password'] = Hash::make($dto->password);
            }

            $this->users->update($user, $payload);
            $this->users->syncRoles($user, $dto->roleNames);

            return $this->users->fresh($user);
        });
    }

    public function toggleStatus(User $user, bool $active, User $actor): User
    {
        if ($actor->id === $user->id && ! $active) {
            throw ValidationException::withMessages([
                'is_active' => 'You cannot deactivate your own account.',
            ]);
        }

        return DB::transaction(function () use ($user, $active) {
            $this->users->update($user, ['is_active' => $active]);

            return $this->users->fresh($user);
        });
    }

    public function delete(User $user, User $actor): void
    {
        if ($actor->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        DB::transaction(function () use ($user) {
            $this->users->detachRoles($user);
            $this->users->delete($user);
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

            $this->users->update($user, $payload);

            return $this->users->fresh($user);
        });
    }
}
