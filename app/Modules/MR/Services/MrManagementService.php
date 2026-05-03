<?php

namespace App\Modules\MR\Services;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\MR\Models\MedicalRepresentative;
use App\Modules\MR\Models\RepresentativeVisit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MrManagementService
{
    private const REPRESENTATIVE_SORTS = [
        'name' => 'name',
        'employee_code' => 'employee_code',
        'territory' => 'territory',
        'monthly_target' => 'monthly_target',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    private const VISIT_SORTS = [
        'visit_date' => 'visit_date',
        'status' => 'status',
        'order_value' => 'order_value',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function representatives(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        $query = MedicalRepresentative::query()
            ->with(['branch:id,name,type', 'employee:id,name,employee_code,designation', 'area:id,name,code', 'division:id,name,code'])
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user && $this->isRepresentativeUser($user), fn (Builder $builder) => $builder->whereKey($user->medical_representative_id))
            ->when($table->search, function (Builder $builder, string $search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('employee_code', 'like', '%'.$search.'%')
                        ->orWhere('territory', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            })
            ->when(array_key_exists('is_active', $table->filters), fn (Builder $builder) => $builder->where('is_active', (bool) $table->filters['is_active']))
            ->when(array_key_exists('branch_id', $table->filters) && $table->filters['branch_id'], fn (Builder $builder) => $builder->where('branch_id', $table->filters['branch_id']))
            ->when(array_key_exists('area_id', $table->filters) && $table->filters['area_id'], fn (Builder $builder) => $builder->where('area_id', $table->filters['area_id']))
            ->when(array_key_exists('division_id', $table->filters) && $table->filters['division_id'], fn (Builder $builder) => $builder->where('division_id', $table->filters['division_id']));

        return $query->orderBy(self::REPRESENTATIVE_SORTS[$table->sortField] ?? 'updated_at', $table->sortOrder)
            ->paginate($table->perPage, ['*'], 'page', $table->page);
    }

    public function visits(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        $query = RepresentativeVisit::query()
            ->with(['medicalRepresentative:id,name', 'employee:id,name,employee_code', 'customer:id,name'])
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user && $this->isRepresentativeUser($user), fn (Builder $builder) => $builder->where('medical_representative_id', $user->medical_representative_id))
            ->when($table->search, function (Builder $builder, string $search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('status', 'like', '%'.$search.'%')
                        ->orWhere('notes', 'like', '%'.$search.'%')
                        ->orWhereHas('medicalRepresentative', fn (Builder $mrQuery) => $mrQuery->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when(array_key_exists('medical_representative_id', $table->filters), fn (Builder $builder) => $builder->where('medical_representative_id', $table->filters['medical_representative_id']))
            ->when(array_key_exists('employee_id', $table->filters), fn (Builder $builder) => $builder->where('employee_id', $table->filters['employee_id']))
            ->when(array_key_exists('status', $table->filters), fn (Builder $builder) => $builder->where('status', $table->filters['status']));

        return $query->orderBy(self::VISIT_SORTS[$table->sortField] ?? 'updated_at', $table->sortOrder)
            ->orderByDesc('id')
            ->paginate($table->perPage, ['*'], 'page', $table->page);
    }

    public function createRepresentative(array $data, User $user): MedicalRepresentative
    {
        return DB::transaction(function () use ($data, $user) {
            return MedicalRepresentative::query()->create([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->company_id,
                'branch_id' => $data['branch_id'] ?? null,
                'employee_id' => $data['employee_id'] ?? null,
                'area_id' => $data['area_id'] ?? null,
                'division_id' => $data['division_id'] ?? null,
                'name' => $data['name'],
                'employee_code' => $data['employee_code'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'territory' => $data['territory'] ?? null,
                'monthly_target' => $data['monthly_target'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }

    public function updateRepresentative(MedicalRepresentative $representative, array $data, User $user): MedicalRepresentative
    {
        return DB::transaction(function () use ($representative, $data, $user) {
            $representative->update([
                'branch_id' => $data['branch_id'] ?? $representative->branch_id,
                'employee_id' => $data['employee_id'] ?? null,
                'area_id' => $data['area_id'] ?? null,
                'division_id' => $data['division_id'] ?? null,
                'name' => $data['name'],
                'employee_code' => $data['employee_code'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'territory' => $data['territory'] ?? null,
                'monthly_target' => $data['monthly_target'] ?? 0,
                'is_active' => $data['is_active'] ?? $representative->is_active,
                'updated_by' => $user->id,
            ]);

            return $representative->fresh(['branch:id,name', 'employee:id,name,employee_code,designation', 'area:id,name,code', 'division:id,name,code']);
        });
    }

    public function deleteRepresentative(MedicalRepresentative $representative, User $user): void
    {
        DB::transaction(function () use ($representative, $user) {
            $representative->forceFill([
                'is_active' => false,
                'updated_by' => $user->id,
            ])->save();
            $representative->delete();
        });
    }

    public function createVisit(array $data, User $user): RepresentativeVisit
    {
        return DB::transaction(function () use ($data, $user) {
            $representativeId = $this->resolveRepresentativeId($data, $user);

            return RepresentativeVisit::query()->create([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->company_id,
                'medical_representative_id' => $representativeId,
                'employee_id' => $data['employee_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'visit_date' => $data['visit_date'],
                'visit_time' => $data['visit_time'] ?? null,
                'status' => $data['status'],
                'purpose' => $data['purpose'] ?? null,
                'order_value' => $data['order_value'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'location_name' => $data['location_name'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ])->load(['medicalRepresentative:id,name', 'employee:id,name,employee_code', 'customer:id,name']);
        });
    }

    public function updateVisit(RepresentativeVisit $visit, array $data, User $user): RepresentativeVisit
    {
        return DB::transaction(function () use ($visit, $data, $user) {
            $representativeId = $this->resolveRepresentativeId($data, $user);

            $visit->update([
                'tenant_id' => $visit->tenant_id ?: $user->tenant_id,
                'company_id' => $visit->company_id ?: $user->company_id,
                'medical_representative_id' => $representativeId,
                'employee_id' => $data['employee_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'visit_date' => $data['visit_date'],
                'visit_time' => $data['visit_time'] ?? null,
                'status' => $data['status'],
                'purpose' => $data['purpose'] ?? null,
                'order_value' => $data['order_value'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'location_name' => $data['location_name'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'updated_by' => $user->id,
            ]);

            return $visit->fresh(['medicalRepresentative:id,name', 'employee:id,name,employee_code', 'customer:id,name']);
        });
    }

    public function deleteVisit(RepresentativeVisit $visit): void
    {
        $visit->delete();
    }

    private function isRepresentativeUser(User $user): bool
    {
        return $user->hasRole('MR') && (int) $user->medical_representative_id > 0;
    }

    private function resolveRepresentativeId(array $data, User $user): int
    {
        if ($this->isRepresentativeUser($user)) {
            return (int) $user->medical_representative_id;
        }

        return (int) $data['medical_representative_id'];
    }
}
