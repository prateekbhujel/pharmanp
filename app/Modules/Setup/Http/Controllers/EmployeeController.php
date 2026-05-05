<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Modules\Setup\Http\Requests\EmployeeRequest;
use App\Modules\Setup\Http\Resources\EmployeeResource;
use App\Modules\Setup\Models\Employee;
use App\Modules\Setup\Services\OrganizationStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class EmployeeController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/setup/employees",
     *     summary="Api Employees Index",
     *     tags={"SETUP - Employees"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request, OrganizationStructureService $service): JsonResponse
    {
        $service->assertMayManageEmployees($request->user());

        $page = $service->employees(TableQueryData::fromRequest($request, [
            'branch_id',
            'area_id',
            'division_id',
            'is_active',
            'deleted',
        ]), $request->user());

        return response()->json(EmployeeResource::collection($page)->response()->getData(true));
    }

    /**
     * @OA\Post(
     *     path="/setup/employees",
     *     summary="Api Employees Store",
     *     tags={"SETUP - Employees"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(EmployeeRequest $request, OrganizationStructureService $service): JsonResponse
    {
        $employee = $service->saveEmployee(new Employee, $request->validated(), $request->user());

        return (new EmployeeResource($employee))
            ->additional(['message' => 'Employee created.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Put(
     *     path="/setup/employees/{employee}",
     *     summary="Api Employees Update",
     *     tags={"SETUP - Employees"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(EmployeeRequest $request, Employee $employee, OrganizationStructureService $service): EmployeeResource
    {
        return new EmployeeResource($service->saveEmployee($employee, $request->validated(), $request->user()));
    }

    /**
     * @OA\Delete(
     *     path="/setup/employees/{employee}",
     *     summary="Api Employees Destroy",
     *     tags={"SETUP - Employees"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Request $request, Employee $employee, OrganizationStructureService $service): JsonResponse
    {
        $service->assertMayManageEmployees($request->user());
        $service->deleteEmployee($employee, $request->user());

        return response()->json(['message' => 'Employee deleted.']);
    }

    /**
     * @OA\Post(
     *     path="/setup/employees/{id}/restore",
     *     summary="Api Setup Employees Restore",
     *     tags={"SETUP - Employees"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function restore(Request $request, int $id, OrganizationStructureService $service): JsonResponse
    {
        $service->assertMayManageEmployees($request->user());

        $employee = $service->restoreEmployee($id, $request->user());

        return (new EmployeeResource($employee))
            ->additional(['message' => 'Employee restored.'])
            ->response();
    }

    /**
     * @OA\Get(
     *     path="/setup/employees/options",
     *     summary="Api Setup Employees Options",
     *     tags={"SETUP - Employees"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function options(Request $request, OrganizationStructureService $service): JsonResponse
    {
        return response()->json(['data' => $service->options('employees', $request->user(), $request->query('search'))]);
    }
}
