<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Setup\Http\Requests\RoleStoreRequest;
use App\Modules\Setup\Http\Requests\RoleUpdateRequest;
use App\Modules\Setup\Services\AccessControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function index(Request $request, AccessControlService $accessControl): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('roles.manage'), 403);
        $accessControl->syncPermissions();
        $catalog = $accessControl->permissionCatalog();

        return response()->json([
            'data' => [
                'permissions' => $accessControl->permissionOptions(),
                'permission_groups' => $catalog,
                'roles' => Role::query()
                    ->where('guard_name', 'web')
                    ->with('permissions:id,name')
                    ->withCount('users')
                    ->orderBy('name')
                    ->get()
                    ->map(function (Role $role) use ($accessControl) {
                        $permissionNames = $role->permissions->pluck('name')->values()->all();

                        return [
                            'id' => $role->id,
                            'name' => $role->name,
                            'locked' => in_array($role->name, ['Owner'], true),
                            'user_count' => $role->users_count,
                            'permission_count' => count($permissionNames),
                            'permissions' => $permissionNames,
                            'summary' => $accessControl->summarize($permissionNames),
                        ];
                    }),
            ],
        ]);
    }

    public function store(RoleStoreRequest $request, AccessControlService $accessControl): JsonResponse
    {
        $accessControl->syncPermissions();

        $role = Role::query()->create(['name' => $request->validated('name'), 'guard_name' => 'web']);
        $role->syncPermissions($request->validated('permissions', []));

        return response()->json(['message' => 'Role created.'], 201);
    }

    public function update(RoleUpdateRequest $request, Role $role): JsonResponse
    {
        $role->update(['name' => $request->validated('name')]);
        $role->syncPermissions($request->validated('permissions', []));

        return response()->json(['message' => 'Role updated.']);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('roles.manage'), 403);
        abort_if(in_array($role->name, ['Owner'], true), 422, 'Owner role cannot be deleted.');

        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }
}
