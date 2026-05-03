<?php

namespace App\Modules\MR\Repositories;

use App\Core\DTOs\TableQueryData;
use App\Core\Query\TableQueryApplier;
use App\Models\User;
use App\Modules\MR\Models\Branch;
use App\Modules\MR\Repositories\Interfaces\BranchRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BranchRepository implements BranchRepositoryInterface
{
    private const SORTS = [
        'name' => 'name',
        'code' => 'code',
        'type' => 'type',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function __construct(private readonly TableQueryApplier $tables) {}

    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        $query = Branch::query()
            ->with('parent:id,name,code')
            ->withCount('medicalRepresentatives')
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user?->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId));

        $this->tables->softDeletes($query, $table);

        $query
            ->when($table->search, function (Builder $builder, string $search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $this->tables->search($inner, $search, ['name', 'code', 'address', 'phone']);
                });
            })
            ->when($table->filters['type'] ?? null, fn (Builder $builder, mixed $type) => $builder->where('type', $type))
            ->when(array_key_exists('is_active', $table->filters), fn (Builder $builder) => $builder->where('is_active', (bool) $table->filters['is_active']));

        return $this->tables->paginate(
            $query
                ->orderByRaw("CASE WHEN type = 'hq' THEN 0 ELSE 1 END")
                ->orderBy($this->tables->sortColumn($table, self::SORTS, 'updated_at'), $table->sortOrder),
            $table,
        );
    }

    public function create(array $payload): Branch
    {
        return Branch::query()->create($payload);
    }

    public function save(Branch $branch, array $payload): Branch
    {
        $branch->forceFill($payload)->save();

        return $branch;
    }

    public function trashed(int $id, ?User $user = null): Branch
    {
        return Branch::query()
            ->onlyTrashed()
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user?->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->findOrFail($id);
    }

    public function parentExists(int $parentId, ?User $user = null, ?int $ignoreId = null): bool
    {
        return Branch::query()
            ->whereKey($parentId)
            ->where('type', 'hq')
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user?->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->when($ignoreId, fn (Builder $builder, int $id) => $builder->whereKeyNot($id))
            ->exists();
    }

    public function parentOptions(?User $user = null): Collection
    {
        return Branch::query()
            ->where('type', 'hq')
            ->where('is_active', true)
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user?->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    public function options(?User $user = null): Collection
    {
        return Branch::query()
            ->where('is_active', true)
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user?->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->orderByRaw("CASE WHEN type = 'hq' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);
    }

    public function hasAssignedRepresentatives(Branch $branch): bool
    {
        return $branch->medicalRepresentatives()->exists();
    }

    public function hasChildren(Branch $branch): bool
    {
        return $branch->children()->exists();
    }
}
