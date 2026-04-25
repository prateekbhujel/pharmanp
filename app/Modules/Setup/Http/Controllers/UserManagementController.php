<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Core\Support\WorkspaceScope;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\MR\Models\MedicalRepresentative;
use App\Modules\Setup\Http\Requests\UserIndexRequest;
use App\Modules\Setup\Http\Requests\UserStoreRequest;
use App\Modules\Setup\Http\Requests\UserUpdateRequest;
use App\Modules\Setup\Http\Resources\UserResource;
use App\Modules\Setup\Services\AccessControlService;
use App\Modules\Setup\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function index(UserIndexRequest $request, UserManagementService $service, AccessControlService $accessControl): JsonResponse
    {
        $users = $service->paginate(TableQueryData::fromRequest($request, ['is_active', 'is_owner', 'medical_representative_linked', 'role_name']), $request->user());
        $payload = UserResource::collection($users)->response()->getData(true);
        $actor = $request->user();
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get(['id', 'name']);

        $payload['lookups'] = [
            'roles' => $roles->map(fn (Role $role) => ['id' => $role->id, 'name' => $role->name])->values(),
            'medical_representatives' => MedicalRepresentative::query()
                ->when($actor, fn ($query) => WorkspaceScope::apply($query, $actor, 'medical_representatives', ['tenant_id', 'company_id']))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
        $payload['summary'] = $service->summary($actor);
        $payload['role_profiles'] = $roles->map(function (Role $role) use ($accessControl, $actor) {
            $permissionNames = $role->permissions->pluck('name')->all();
            $userCount = User::query()
                ->whereHas('roles', fn ($query) => $query->where('roles.id', $role->id));
            WorkspaceScope::apply($userCount, $actor, 'users', ['tenant_id', 'company_id', 'store_id']);

            return [
                'id' => $role->id,
                'name' => $role->name,
                'user_count' => $userCount->count(),
                'permission_count' => count($permissionNames),
                'summary' => $accessControl->summarize($permissionNames),
            ];
        })->values();

        return response()->json($payload);
    }

    public function store(UserStoreRequest $request, UserManagementService $service): JsonResponse
    {
        $user = $service->create($request->validated(), $request->user());

        return (new UserResource($user))
            ->additional(['message' => 'User created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UserUpdateRequest $request, User $user, UserManagementService $service): UserResource
    {
        WorkspaceScope::ensure($user, $request->user(), ['tenant_id', 'company_id', 'store_id']);

        return new UserResource($service->update($user, $request->validated(), $request->user()));
    }

    public function destroy(User $user, UserManagementService $service): JsonResponse
    {
        WorkspaceScope::ensure($user, request()->user(), ['tenant_id', 'company_id', 'store_id']);
        $service->delete($user, request()->user());

        return response()->json(['message' => 'User deleted.']);
    }
}
