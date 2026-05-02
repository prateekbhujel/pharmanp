<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\MR\Models\Branch;
use App\Modules\MR\Models\MedicalRepresentative;
use App\Modules\Setup\Http\Requests\UserIndexRequest;
use App\Modules\Setup\Http\Requests\UserStoreRequest;
use App\Modules\Setup\Http\Requests\UserUpdateRequest;
use App\Modules\Setup\Http\Resources\UserResource;
use App\Modules\Setup\Contracts\UserManagementServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function index(UserIndexRequest $request, UserManagementServiceInterface $service): JsonResponse
    {
        $users = $service->paginate(TableQueryData::fromRequest($request, ['is_active', 'role_name']), $request->user());
        $payload = UserResource::collection($users)->response()->getData(true);
        $payload['lookups'] = [
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()
                ->where('is_active', true)
                ->when($request->user()?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
                ->when($request->user()?->company_id, fn ($query, $companyId) => $query->where('company_id', $companyId))
                ->orderByRaw("CASE WHEN type = 'hq' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'type']),
            'medical_representatives' => MedicalRepresentative::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];

        return response()->json($payload);
    }

    public function store(UserStoreRequest $request, UserManagementServiceInterface $service): JsonResponse
    {
        $user = $service->create($request->validated(), $request->user());

        return (new UserResource($user))
            ->additional(['message' => 'User created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UserUpdateRequest $request, User $user, UserManagementServiceInterface $service): UserResource
    {
        return new UserResource($service->update($user, $request->validated(), $request->user()));
    }

    public function toggleStatus(Request $request, User $user, UserManagementServiceInterface $service): UserResource
    {
        abort_unless((bool) $request->user()?->is_owner || (bool) $request->user()?->can('users.manage'), 403);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        return new UserResource($service->toggleStatus($user, (bool) $validated['is_active'], $request->user()));
    }

    public function destroy(User $user, UserManagementServiceInterface $service): JsonResponse
    {
        $service->delete($user, request()->user());

        return response()->json(['message' => 'User deleted.']);
    }
}
