<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\Controller;
use App\Modules\Setup\Contracts\OrganizationStructureServiceInterface;
use App\Modules\Setup\Http\Requests\EmployeeRequest;
use App\Modules\Setup\Http\Resources\EmployeeResource;
use App\Modules\Setup\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function index(Request $request, OrganizationStructureServiceInterface $service): JsonResponse
    {
        $this->authorizeManage($request);

        $page = $service->employees(TableQueryData::fromRequest($request, [
            'branch_id',
            'area_id',
            'division_id',
            'is_active',
            'deleted',
        ]), $request->user());

        return response()->json(EmployeeResource::collection($page)->response()->getData(true));
    }

    public function store(EmployeeRequest $request, OrganizationStructureServiceInterface $service): JsonResponse
    {
        $employee = $service->saveEmployee(new Employee(), $request->validated(), $request->user());

        return (new EmployeeResource($employee))
            ->additional(['message' => 'Employee created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(EmployeeRequest $request, Employee $employee, OrganizationStructureServiceInterface $service): EmployeeResource
    {
        return new EmployeeResource($service->saveEmployee($employee, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, Employee $employee, OrganizationStructureServiceInterface $service): JsonResponse
    {
        $this->authorizeManage($request);
        $service->deleteEmployee($employee, $request->user());

        return response()->json(['message' => 'Employee deleted.']);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request);

        $employee = Employee::query()
            ->onlyTrashed()
            ->when($request->user()?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->findOrFail($id);

        DB::transaction(function () use ($employee, $request) {
            $employee->restore();
            $employee->forceFill(['is_active' => true, 'updated_by' => $request->user()->id])->save();
        });

        return (new EmployeeResource($employee->fresh(['user:id,name,email', 'branch:id,name,code,type', 'area:id,name,code', 'division:id,name,code', 'manager:id,name,employee_code'])))
            ->additional(['message' => 'Employee restored.'])
            ->response();
    }

    public function options(Request $request, OrganizationStructureServiceInterface $service): JsonResponse
    {
        return response()->json(['data' => $service->options('employees', $request->user(), $request->query('search'))]);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('users.manage') || $request->user()?->can('mr.manage'), 403);
    }
}
