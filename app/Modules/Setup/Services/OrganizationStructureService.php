<?php

namespace App\Modules\Setup\Services;

use App\Core\DTOs\TableQueryData;
use App\Core\Services\EmployeeCodeGenerator;
use App\Models\User;
use App\Modules\MR\Models\Branch;
use App\Modules\Setup\Contracts\OrganizationStructureServiceInterface;
use App\Modules\Setup\Models\Area;
use App\Modules\Setup\Models\Division;
use App\Modules\Setup\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationStructureService implements OrganizationStructureServiceInterface
{
    private const AREA_SORTS = [
        'name' => 'name',
        'code' => 'code',
        'district' => 'district',
        'province' => 'province',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    private const DIVISION_SORTS = [
        'name' => 'name',
        'code' => 'code',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    private const EMPLOYEE_SORTS = [
        'name' => 'name',
        'employee_code' => 'employee_code',
        'designation' => 'designation',
        'joined_on' => 'joined_on',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function __construct(
        private readonly EmployeeCodeGenerator $employeeCodes,
    ) {}

    public function areas(TableQueryData $table, User $user): LengthAwarePaginator
    {
        $query = Area::query()
            ->with('branch:id,name,code,type')
            ->when($user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->when($table->filters['branch_id'] ?? null, fn (Builder $builder, mixed $branchId) => $builder->where('branch_id', $branchId))
            ->when(array_key_exists('is_active', $table->filters), fn (Builder $builder) => $builder->where('is_active', (bool) $table->filters['is_active']))
            ->when($table->filters['deleted'] ?? null, fn (Builder $builder) => $builder->onlyTrashed())
            ->when($table->search, function (Builder $builder, string $search) {
                $builder->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%')
                        ->orWhere('district', 'like', '%'.$search.'%')
                        ->orWhere('province', 'like', '%'.$search.'%');
                });
            });

        return $query
            ->orderBy(self::AREA_SORTS[$table->sortField] ?? 'updated_at', $table->sortOrder)
            ->orderByDesc('id')
            ->paginate($table->perPage, ['*'], 'page', $table->page);
    }

    public function saveArea(Area $area, array $data, User $user): Area
    {
        $this->assertBranch($data['branch_id'], $user);

        return DB::transaction(function () use ($area, $data, $user) {
            $area->fill([
                'tenant_id' => $area->tenant_id ?: $user->tenant_id,
                'company_id' => $area->company_id ?: $user->company_id,
                'branch_id' => $data['branch_id'],
                'name' => $data['name'],
                'code' => filled($data['code'] ?? null) ? strtoupper(trim((string) $data['code'])) : null,
                'district' => $data['district'] ?? null,
                'province' => $data['province'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'updated_by' => $user->id,
            ]);

            if (! $area->exists) {
                $area->created_by = $user->id;
            }

            $area->save();

            return $area->fresh('branch:id,name,code,type');
        });
    }

    public function deleteArea(Area $area, User $user): void
    {
        $this->assertSameCompany($area, $user);

        if ($area->employees()->exists()) {
            throw ValidationException::withMessages(['area' => 'Move assigned employees before deleting this area.']);
        }

        DB::transaction(function () use ($area, $user) {
            $area->forceFill(['is_active' => false, 'updated_by' => $user->id])->save();
            $area->delete();
        });
    }

    public function divisions(TableQueryData $table, User $user): LengthAwarePaginator
    {
        $query = Division::query()
            ->withCount(['products', 'employees'])
            ->when($user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->when(array_key_exists('is_active', $table->filters), fn (Builder $builder) => $builder->where('is_active', (bool) $table->filters['is_active']))
            ->when($table->filters['deleted'] ?? null, fn (Builder $builder) => $builder->onlyTrashed())
            ->when($table->search, function (Builder $builder, string $search) {
                $builder->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%');
                });
            });

        return $query
            ->orderBy(self::DIVISION_SORTS[$table->sortField] ?? 'updated_at', $table->sortOrder)
            ->orderByDesc('id')
            ->paginate($table->perPage, ['*'], 'page', $table->page);
    }

    public function saveDivision(Division $division, array $data, User $user): Division
    {
        return DB::transaction(function () use ($division, $data, $user) {
            $division->fill([
                'tenant_id' => $division->tenant_id ?: $user->tenant_id,
                'company_id' => $division->company_id ?: $user->company_id,
                'name' => $data['name'],
                'code' => filled($data['code'] ?? null) ? strtoupper(trim((string) $data['code'])) : null,
                'notes' => $data['notes'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'updated_by' => $user->id,
            ]);

            if (! $division->exists) {
                $division->created_by = $user->id;
            }

            $division->save();

            return $division->fresh();
        });
    }

    public function deleteDivision(Division $division, User $user): void
    {
        $this->assertSameCompany($division, $user);

        if ($division->products()->exists() || $division->employees()->exists()) {
            throw ValidationException::withMessages(['division' => 'Move products and employees before deleting this division.']);
        }

        DB::transaction(function () use ($division, $user) {
            $division->forceFill(['is_active' => false, 'updated_by' => $user->id])->save();
            $division->delete();
        });
    }

    public function employees(TableQueryData $table, User $user): LengthAwarePaginator
    {
        $query = Employee::query()
            ->with(['user:id,name,email', 'branch:id,name,code,type', 'area:id,name,code', 'division:id,name,code', 'manager:id,name,employee_code'])
            ->when($user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->when($table->filters['branch_id'] ?? null, fn (Builder $builder, mixed $branchId) => $builder->where('branch_id', $branchId))
            ->when($table->filters['area_id'] ?? null, fn (Builder $builder, mixed $areaId) => $builder->where('area_id', $areaId))
            ->when($table->filters['division_id'] ?? null, fn (Builder $builder, mixed $divisionId) => $builder->where('division_id', $divisionId))
            ->when(array_key_exists('is_active', $table->filters), fn (Builder $builder) => $builder->where('is_active', (bool) $table->filters['is_active']))
            ->when($table->filters['deleted'] ?? null, fn (Builder $builder) => $builder->onlyTrashed())
            ->when($table->search, function (Builder $builder, string $search) {
                $builder->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('employee_code', 'like', '%'.$search.'%')
                        ->orWhere('designation', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            });

        return $query
            ->orderBy(self::EMPLOYEE_SORTS[$table->sortField] ?? 'updated_at', $table->sortOrder)
            ->orderByDesc('id')
            ->paginate($table->perPage, ['*'], 'page', $table->page);
    }

    public function saveEmployee(Employee $employee, array $data, User $user): Employee
    {
        $this->assertNullableScoped($data['branch_id'] ?? null, Branch::query(), $user, 'branch_id');
        $this->assertNullableScoped($data['area_id'] ?? null, Area::query(), $user, 'area_id');
        $this->assertNullableScoped($data['division_id'] ?? null, Division::query(), $user, 'division_id');

        if (! empty($data['reports_to_employee_id']) && $employee->exists && (int) $data['reports_to_employee_id'] === (int) $employee->id) {
            throw ValidationException::withMessages(['reports_to_employee_id' => 'An employee cannot report to themselves.']);
        }

        return DB::transaction(function () use ($employee, $data, $user) {
            $employee->fill([
                'tenant_id' => $employee->tenant_id ?: $user->tenant_id,
                'company_id' => $employee->company_id ?: $user->company_id,
                'user_id' => $data['user_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'area_id' => $data['area_id'] ?? null,
                'division_id' => $data['division_id'] ?? null,
                'reports_to_employee_id' => $data['reports_to_employee_id'] ?? null,
                'employee_code' => $employee->employee_code ?: ($data['employee_code'] ?? $this->employeeCodes->next()),
                'name' => $data['name'],
                'designation' => $data['designation'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'joined_on' => $data['joined_on'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'updated_by' => $user->id,
            ]);

            if (! $employee->exists) {
                $employee->created_by = $user->id;
            }

            $employee->save();

            return $employee->fresh(['user:id,name,email', 'branch:id,name,code,type', 'area:id,name,code', 'division:id,name,code', 'manager:id,name,employee_code']);
        });
    }

    public function deleteEmployee(Employee $employee, User $user): void
    {
        $this->assertSameCompany($employee, $user);

        if ($employee->subordinates()->exists()) {
            throw ValidationException::withMessages(['employee' => 'Reassign subordinate employees before deleting this employee.']);
        }

        DB::transaction(function () use ($employee, $user) {
            $employee->forceFill(['is_active' => false, 'updated_by' => $user->id])->save();
            $employee->delete();
        });
    }

    public function options(string $type, User $user, ?string $search = null): Collection
    {
        $query = match ($type) {
            'areas' => Area::query()->where('is_active', true)->select(['id', 'name', 'code', 'branch_id']),
            'divisions' => Division::query()->where('is_active', true)->select(['id', 'name', 'code']),
            'employees' => Employee::query()->where('is_active', true)->select(['id', 'name', 'employee_code', 'designation']),
            default => throw ValidationException::withMessages(['type' => 'Unsupported option type.']),
        };

        $query
            ->when($user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->when($search, fn (Builder $builder, string $keyword) => $builder->where('name', 'like', '%'.$keyword.'%'))
            ->orderBy('name')
            ->limit(50);

        return $query->get();
    }

    private function assertBranch(int $branchId, User $user): void
    {
        $this->assertNullableScoped($branchId, Branch::query(), $user, 'branch_id');
    }

    private function assertNullableScoped(mixed $id, Builder $query, User $user, string $field): void
    {
        if (! $id) {
            return;
        }

        $exists = $query
            ->when($user->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($user->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
            ->whereKey($id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([$field => 'Selected record is not available in this company.']);
        }
    }

    private function assertSameCompany(object $model, User $user): void
    {
        if ($user->canAccessAllTenants()) {
            return;
        }

        abort_unless(! $user->company_id || (int) $model->company_id === (int) $user->company_id, 404);
    }
}
