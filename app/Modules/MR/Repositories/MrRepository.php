<?php

namespace App\Modules\MR\Repositories;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\MR\Models\MedicalRepresentative;
use App\Modules\MR\Models\RepresentativeVisit;
use App\Modules\MR\Repositories\Interfaces\MrRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MrRepository implements MrRepositoryInterface
{
    private const REPRESENTATIVE_SORTS = [
        'name' => 'name',
        'employee_code' => 'employee_code',
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
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhereHas('area', fn (Builder $areaQuery) => $areaQuery->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%'))
                        ->orWhereHas('division', fn (Builder $divisionQuery) => $divisionQuery->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%'));
                });
            })
            ->when(array_key_exists('is_active', $table->filters), fn (Builder $builder) => $builder->where('is_active', (bool) $table->filters['is_active']))
            ->when(! empty($table->filters['branch_id']), fn (Builder $builder) => $builder->where('branch_id', $table->filters['branch_id']))
            ->when(! empty($table->filters['area_id']), fn (Builder $builder) => $builder->where('area_id', $table->filters['area_id']))
            ->when(! empty($table->filters['division_id']), fn (Builder $builder) => $builder->where('division_id', $table->filters['division_id']));

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

    public function createRepresentative(array $data): MedicalRepresentative
    {
        return MedicalRepresentative::query()->create($data);
    }

    public function updateRepresentative(MedicalRepresentative $representative, array $data): MedicalRepresentative
    {
        $representative->update($data);

        return $representative->fresh(['branch:id,name', 'employee:id,name,employee_code,designation', 'area:id,name,code', 'division:id,name,code']);
    }

    public function deleteRepresentative(MedicalRepresentative $representative): void
    {
        $representative->delete();
    }

    public function createVisit(array $data): RepresentativeVisit
    {
        return RepresentativeVisit::query()
            ->create($data)
            ->load(['medicalRepresentative:id,name', 'employee:id,name,employee_code', 'customer:id,name']);
    }

    public function updateVisit(RepresentativeVisit $visit, array $data): RepresentativeVisit
    {
        $visit->update($data);

        return $visit->fresh(['medicalRepresentative:id,name', 'employee:id,name,employee_code', 'customer:id,name']);
    }

    public function deleteVisit(RepresentativeVisit $visit): void
    {
        $visit->delete();
    }

    public function representativeOptions(?User $user = null): Collection
    {
        return MedicalRepresentative::query()
            ->where('is_active', true)
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user && $this->isRepresentativeUser($user), fn (Builder $builder) => $builder->whereKey($user->medical_representative_id))
            ->orderBy('name')
            ->with(['area:id,name,code', 'division:id,name,code'])
            ->get(['id', 'name', 'employee_code', 'area_id', 'division_id', 'monthly_target']);
    }

    public function isRepresentativeUser(User $user): bool
    {
        return $user->hasRole('MR') && (int) $user->medical_representative_id > 0;
    }
}
