<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\MR\Models\MedicalRepresentative;
use App\Modules\Setup\Http\Requests\UserIndexRequest;
use App\Modules\Setup\Http\Requests\UserStoreRequest;
use App\Modules\Setup\Http\Requests\UserUpdateRequest;
use App\Modules\Setup\Http\Resources\UserResource;
use App\Modules\Setup\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function index(UserIndexRequest $request, UserManagementService $service): JsonResponse
    {
        $users = $service->paginate(TableQueryData::fromRequest($request, ['is_active', 'role_name']));
        $payload = UserResource::collection($users)->response()->getData(true);
        $payload['lookups'] = [
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
            'medical_representatives' => MedicalRepresentative::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];

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
        return new UserResource($service->update($user, $request->validated(), $request->user()));
    }

    public function destroy(User $user, UserManagementService $service): JsonResponse
    {
        $service->delete($user, request()->user());

        return response()->json(['message' => 'User deleted.']);
    }
}
