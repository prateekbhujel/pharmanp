<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Models\User;
use App\Modules\MR\Models\Branch;
use App\Modules\MR\Models\MedicalRepresentative;
use App\Modules\Setup\Http\Requests\UserIndexRequest;
use App\Modules\Setup\Http\Requests\UserStoreRequest;
use App\Modules\Setup\Http\Requests\UserUpdateRequest;
use App\Modules\Setup\Http\Resources\UserResource;
use App\Modules\Setup\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class UserManagementController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/setup/users",
     *     summary="Api Setup Users Index",
     *     tags={"SETUP - Users"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(UserIndexRequest $request, UserManagementService $service): JsonResponse
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

    /**
     * @OA\Post(
     *     path="/setup/users",
     *     summary="Api Setup Users Store",
     *     tags={"SETUP - Users"},
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
    public function store(UserStoreRequest $request, UserManagementService $service): JsonResponse
    {
        $user = $service->create($request->validated(), $request->user());

        return (new UserResource($user))
            ->additional(['message' => 'User created.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Put(
     *     path="/setup/users/{user}",
     *     summary="Api Setup Users Update",
     *     tags={"SETUP - Users"},
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
    public function update(UserUpdateRequest $request, User $user, UserManagementService $service): UserResource
    {
        return new UserResource($service->update($user, $request->validated(), $request->user()));
    }

    /**
     * @OA\Patch(
     *     path="/setup/users/{user}/status",
     *     summary="Api Setup Users Status",
     *     tags={"SETUP - Users"},
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
    public function toggleStatus(Request $request, User $user, UserManagementService $service): UserResource
    {
        abort_unless((bool) $request->user()?->is_owner || (bool) $request->user()?->can('users.manage'), 403);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        return new UserResource($service->toggleStatus($user, (bool) $validated['is_active'], $request->user()));
    }

    /**
     * @OA\Delete(
     *     path="/setup/users/{user}",
     *     summary="Api Setup Users Destroy",
     *     tags={"SETUP - Users"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(User $user, UserManagementService $service): JsonResponse
    {
        $service->delete($user, request()->user());

        return response()->json(['message' => 'User deleted.']);
    }
}
