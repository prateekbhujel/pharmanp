<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Setup\Http\Requests\RoleStoreRequest;
use App\Modules\Setup\Http\Requests\RoleUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_owner, 403);

        return response()->json([
            'data' => [
                'permissions' => Permission::query()->where('guard_name', 'web')->orderBy('name')->pluck('name'),
                'roles' => Role::query()
                    ->where('guard_name', 'web')
                    ->with('permissions:id,name')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Role $role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'permissions' => $role->permissions->pluck('name')->values(),
                    ]),
            ],
        ]);
    }

    public function store(RoleStoreRequest $request): JsonResponse
    {
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
}
