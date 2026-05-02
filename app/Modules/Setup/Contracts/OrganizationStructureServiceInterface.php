<?php

namespace App\Modules\Setup\Contracts;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Setup\Models\Area;
use App\Modules\Setup\Models\Division;
use App\Modules\Setup\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface OrganizationStructureServiceInterface
{
    public function areas(TableQueryData $table, User $user): LengthAwarePaginator;

    public function saveArea(Area $area, array $data, User $user): Area;

    public function deleteArea(Area $area, User $user): void;

    public function divisions(TableQueryData $table, User $user): LengthAwarePaginator;

    public function saveDivision(Division $division, array $data, User $user): Division;

    public function deleteDivision(Division $division, User $user): void;

    public function employees(TableQueryData $table, User $user): LengthAwarePaginator;

    public function saveEmployee(Employee $employee, array $data, User $user): Employee;

    public function deleteEmployee(Employee $employee, User $user): void;

    public function options(string $type, User $user, ?string $search = null): Collection;
}
