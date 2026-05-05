<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Http\Controllers\ModularController;
use App\Modules\Setup\Http\Requests\RoleStoreRequest;
use App\Modules\Setup\Http\Requests\RoleUpdateRequest;
use App\Modules\Setup\Services\AccessControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class RolePermissionController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/setup/roles",
     *     summary="Api Setup Roles Index",
     *     tags={"SETUP - Roles"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request, AccessControlService $accessControl): JsonResponse
    {
        abort_unless($request->user()?->is_owner, 403);
        $accessControl->syncPermissions();

        return response()->json([
            'data' => [
                'permissions' => $accessControl->permissionNames(),
                'permission_groups' => $accessControl->permissionGroups(),
                'roles' => Role::query()
                    ->where('guard_name', 'web')
                    ->with('permissions:id,name')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Role $role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'locked' => in_array($role->name, ['Owner'], true),
                        'permissions' => $role->permissions->pluck('name')->values(),
                    ]),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/setup/roles",
     *     summary="Api Setup Roles Store",
     *     tags={"SETUP - Roles"},
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
    public function store(RoleStoreRequest $request, AccessControlService $accessControl): JsonResponse
    {
        $accessControl->syncPermissions();

        $role = Role::query()->create(['name' => $request->validated('name'), 'guard_name' => 'web']);
        $role->syncPermissions($request->validated('permissions', []));

        return response()->json(['message' => 'Role created.'], 201);
    }

    /**
     * @OA\Put(
     *     path="/setup/roles/{role}",
     *     summary="Api Setup Roles Update",
     *     tags={"SETUP - Roles"},
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
    public function update(RoleUpdateRequest $request, Role $role): JsonResponse
    {
        $role->update(['name' => $request->validated('name')]);
        $role->syncPermissions($request->validated('permissions', []));

        return response()->json(['message' => 'Role updated.']);
    }

    /**
     * @OA\Delete(
     *     path="/setup/roles/{role}",
     *     summary="Api Setup Roles Destroy",
     *     tags={"SETUP - Roles"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        abort_unless($request->user()?->is_owner, 403);
        abort_if(in_array($role->name, ['Owner'], true), 422, 'Owner role cannot be deleted.');

        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }
}
