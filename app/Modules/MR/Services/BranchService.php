<?php

namespace App\Modules\MR\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Core\Support\ApiResponse;
use App\Models\User;
use App\Modules\MR\Models\Branch;
use App\Modules\MR\Repositories\Interfaces\BranchRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchService
{
    public function __construct(private readonly BranchRepositoryInterface $branches) {}

    public function table(TableQueryData $table, User $user): array
    {
        $this->ensureCanManage($user);
        $page = $this->branches->paginate($table, $user);

        return [
            'data' => collect($page->items())->map(fn (Branch $branch) => $this->payload($branch))->values(),
            'meta' => ApiResponse::paginationMeta($page),
            'lookups' => [
                'parents' => $this->parentOptions($user),
            ],
        ];
    }

    public function create(array $data, User $user): Branch
    {
        $this->ensureCanManage($user);
        $data = $this->validatedDomainPayload($data, $user);

        return DB::transaction(fn () => $this->branches->create([
            ...$data,
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'store_id' => $user->store_id,
        ]))->load('parent');
    }

    public function update(Branch $branch, array $data, User $user): Branch
    {
        $this->ensureCanManage($user);
        $this->assertSameScope($user, $branch);
        $data = $this->validatedDomainPayload($data, $user, $branch->id);

        return DB::transaction(fn () => $this->branches->save($branch, $data))->fresh('parent');
    }

    public function toggleStatus(Branch $branch, bool $isActive, User $user): Branch
    {
        $this->ensureCanManage($user);
        $this->assertSameScope($user, $branch);

        return DB::transaction(fn () => $this->branches->save($branch, ['is_active' => $isActive]))->fresh('parent');
    }

    public function delete(Branch $branch, User $user): void
    {
        $this->ensureCanManage($user);
        $this->assertSameScope($user, $branch);

        if ($this->branches->hasAssignedRepresentatives($branch)) {
            throw ValidationException::withMessages(['branch' => 'This branch has assigned MRs. Reassign them first.']);
        }

        if ($this->branches->hasChildren($branch)) {
            throw ValidationException::withMessages(['branch' => 'This branch has child branches. Move or delete child branches first.']);
        }

        DB::transaction(function () use ($branch): void {
            $this->branches->save($branch, ['is_active' => false]);
            $branch->delete();
        });
    }

    public function restore(int $id, User $user): Branch
    {
        $this->ensureCanManage($user);

        return DB::transaction(function () use ($id, $user) {
            $branch = $this->branches->trashed($id, $user);
            $branch->restore();

            return $this->branches->save($branch, ['is_active' => true])->fresh('parent');
        });
    }

    public function options(User $user): array
    {
        return [
            'data' => $this->branches->options($user)->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name.($branch->type === 'hq' ? ' (HQ)' : ''),
                'code' => $branch->code,
                'type' => $branch->type,
            ])->values(),
        ];
    }

    public function payload(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'code' => $branch->code,
            'type' => $branch->type,
            'parent_id' => $branch->parent_id,
            'parent' => $branch->relationLoaded('parent') && $branch->parent ? [
                'id' => $branch->parent->id,
                'name' => $branch->parent->name,
                'code' => $branch->parent->code,
            ] : null,
            'address' => $branch->address,
            'phone' => $branch->phone,
            'is_active' => (bool) $branch->is_active,
            'is_hq' => $branch->is_hq,
            'medical_representatives_count' => (int) ($branch->medical_representatives_count ?? 0),
            'deleted_at' => $branch->deleted_at?->toISOString(),
            'created_at' => $branch->created_at?->toDateString(),
        ];
    }

    private function validatedDomainPayload(array $data, User $user, ?int $ignoreId = null): array
    {
        if (($data['type'] ?? null) === 'hq') {
            $data['parent_id'] = null;
        }

        if (! empty($data['parent_id']) && ! $this->branches->parentExists((int) $data['parent_id'], $user, $ignoreId)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Select a valid HQ branch from this company.',
            ]);
        }

        return [
            ...$data,
            'code' => filled($data['code'] ?? null) ? strtoupper(trim((string) $data['code'])) : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function parentOptions(User $user): array
    {
        return $this->branches->parentOptions($user)
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
            ])
            ->values()
            ->all();
    }

    private function ensureCanManage(User $user): void
    {
        abort_unless(
            $user->is_owner
                || $user->can('setup.manage')
                || $user->can('users.manage')
                || $user->can('mr.manage'),
            403,
        );
    }

    private function assertSameScope(User $user, Branch $branch): void
    {
        if ($user->tenant_id && (int) $branch->tenant_id !== (int) $user->tenant_id) {
            abort(404);
        }

        if ($user->company_id && (int) $branch->company_id !== (int) $user->company_id) {
            abort(404);
        }
    }
}
