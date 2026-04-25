<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Setup\Http\Requests\ProfileUpdateRequest;
use App\Modules\Setup\Http\Resources\UserResource;
use App\Modules\Setup\Services\UserManagementService;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function show(): UserResource
    {
        return new UserResource(request()->user()->load(['roles:id,name', 'medicalRepresentative:id,name']));
    }

    public function update(ProfileUpdateRequest $request, UserManagementService $service): JsonResponse
    {
        $user = $service->updateProfile($request->user(), $request->validated());

        return (new UserResource($user))
            ->additional(['message' => 'Profile updated.'])
            ->response()
            ->setStatusCode(200);
    }
}
