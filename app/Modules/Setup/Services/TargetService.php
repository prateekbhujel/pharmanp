<?php

namespace App\Modules\Setup\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Setup\Models\Target;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TargetService
{
    private const SORTS = [
        'target_type' => 'target_type',
        'target_period' => 'target_period',
        'target_level' => 'target_level',
        'start_date' => 'start_date',
        'end_date' => 'end_date',
        'status' => 'status',
        'updated_at' => 'updated_at',
    ];

    public function targets(TableQueryData $table, User $user): LengthAwarePaginator
    {
        $query = Target::query()
            ->with([
                'branch:id,name,code',
                'area:id,name,code',
                'division:id,name,code',
                'employee:id,name,employee_code',
                'product:id,name,product_code,sku',
            ])
            ->when($user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->when($table->filters['target_type'] ?? null, fn (Builder $builder, mixed $value) => $builder->where('target_type', $value))
            ->when($table->filters['target_period'] ?? null, fn (Builder $builder, mixed $value) => $builder->where('target_period', $value))
            ->when($table->filters['target_level'] ?? null, fn (Builder $builder, mixed $value) => $builder->where('target_level', $value))
            ->when($table->filters['status'] ?? null, fn (Builder $builder, mixed $value) => $builder->where('status', $value))
            ->when($table->filters['deleted'] ?? null, fn (Builder $builder) => $builder->onlyTrashed());

        return $query
            ->orderBy(self::SORTS[$table->sortField] ?? 'updated_at', $table->sortOrder)
            ->orderByDesc('id')
            ->paginate($table->perPage, ['*'], 'page', $table->page);
    }

    public function save(Target $target, array $data, User $user): Target
    {
        return DB::transaction(function () use ($target, $data, $user) {
            $target->fill([
                'tenant_id' => $target->tenant_id ?: $user->tenant_id,
                'company_id' => $target->company_id ?: $user->company_id,
                'branch_id' => $data['branch_id'] ?? null,
                'area_id' => $data['area_id'] ?? null,
                'division_id' => $data['division_id'] ?? null,
                'employee_id' => $data['employee_id'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'target_type' => $data['target_type'],
                'target_period' => $data['target_period'],
                'target_level' => $data['target_level'],
                'target_amount' => $data['target_amount'] ?? null,
                'target_quantity' => $data['target_quantity'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => $data['status'] ?? 'active',
                'notes' => $data['notes'] ?? null,
                'updated_by' => $user->id,
            ]);

            if (! $target->exists) {
                $target->created_by = $user->id;
            }

            $target->save();

            return $target->fresh([
                'branch:id,name,code',
                'area:id,name,code',
                'division:id,name,code',
                'employee:id,name,employee_code',
                'product:id,name,product_code,sku',
            ]);
        });
    }

    public function delete(Target $target, User $user): void
    {
        abort_unless($user->canAccessAllTenants() || ! $user->company_id || (int) $target->company_id === (int) $user->company_id, 404);

        DB::transaction(function () use ($target, $user) {
            $target->forceFill(['status' => 'closed', 'updated_by' => $user->id])->save();
            $target->delete();
        });
    }

    public function restoreTarget(int $id, User $user): Target
    {
        $target = Target::query()
            ->onlyTrashed()
            ->when($user->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($user->company_id, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->findOrFail($id);

        return DB::transaction(function () use ($target, $user) {
            $target->restore();
            $target->forceFill(['status' => 'active', 'updated_by' => $user->id])->save();

            return $target->fresh();
        });
    }

    public function assertMayManageTargets(User $user): void
    {
        abort_unless($user->is_owner || $user->can('mr.manage') || $user->can('reports.view'), 403);
    }
}
