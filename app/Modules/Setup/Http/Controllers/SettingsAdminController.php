<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Modules\Setup\Http\Requests\SettingsAdminRequest;
use App\Modules\Setup\Http\Requests\TestMailRequest;
use App\Modules\Setup\Services\SettingsAdminService;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class SettingsAdminController extends ModularController
{
    public function __construct(private readonly SettingsAdminService $settings) {}

    // Return all app settings for the admin settings form.
    /**
     * @OA\Get(
     *     path="/settings/admin",
     *     summary="Api Settings Admin Show",
     *     tags={"SETUP - Admin"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function show(): JsonResponse
    {
        return $this->success($this->settings->settings(), 'Settings retrieved successfully.');
    }

    // Save admin settings.
    /**
     * @OA\Put(
     *     path="/settings/admin",
     *     summary="Api Settings Admin Update",
     *     tags={"SETUP - Admin"},
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
    public function update(SettingsAdminRequest $request): JsonResponse
    {
        $this->settings->update($request->validated());

        return $this->success(null, 'Settings saved successfully.');
    }

    // Send one test mail to validate SMTP config.
    /**
     * @OA\Post(
     *     path="/settings/admin/test-mail",
     *     summary="Api Settings Admin Test Mail",
     *     tags={"SETUP - Admin"},
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
    public function testMail(TestMailRequest $request): JsonResponse
    {
        $recipient = $this->settings->sendTestMail($request->validated('email'));

        return $this->success(null, 'Test mail sent to '.$recipient.'.');
    }
}
