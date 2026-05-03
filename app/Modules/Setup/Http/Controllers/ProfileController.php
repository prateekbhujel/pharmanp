<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Modules\Setup\Http\Requests\ProfileUpdateRequest;
use App\Modules\Setup\Http\Resources\UserResource;
use App\Modules\Setup\Services\UserManagementService;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class ProfileController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/profile",
     *     summary="Api Profile Show",
     *     tags={"SETUP - Profile"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function show(): UserResource
    {
        return new UserResource(request()->user()->load(['roles:id,name', 'medicalRepresentative:id,name']));
    }

    /**
     * @OA\Put(
     *     path="/profile",
     *     summary="Api Profile Update",
     *     tags={"SETUP - Profile"},
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
    public function update(ProfileUpdateRequest $request, UserManagementService $service): JsonResponse
    {
        $user = $service->updateProfile($request->user(), $request->validated());

        return (new UserResource($user))
            ->additional(['message' => 'Profile updated.'])
            ->response()
            ->setStatusCode(200);
    }
}
