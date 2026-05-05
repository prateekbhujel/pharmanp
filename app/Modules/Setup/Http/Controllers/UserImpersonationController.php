<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\Services\JwtTokenService;
use App\Http\Controllers\ModularController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class UserImpersonationController extends ModularController
{
    public function __construct(private readonly JwtTokenService $jwt) {}

    /**
     * @OA\Post(
     *     path="/setup/users/{user}/impersonate",
     *     summary="Api Setup Users Impersonate Start",
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
    public function start(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        abort_unless((bool) $actor?->is_owner || (bool) $actor?->can('users.manage'), 403);

        if ((int) $actor->id === (int) $user->id) {
            throw ValidationException::withMessages([
                'user' => 'You are already using this account.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'user' => 'Only active users can be impersonated.',
            ]);
        }

        if ($actor->tenant_id && (int) $actor->tenant_id !== (int) $user->tenant_id) {
            abort(404);
        }

        if ($actor->company_id && (int) $actor->company_id !== (int) $user->company_id) {
            abort(404);
        }

        $expiresAt = $this->jwt->defaultExpiry();

        return response()->json([
            'message' => "Now viewing as {$user->name}.",
            'token_type' => 'Bearer',
            'token' => $this->jwt->issue($user, $expiresAt, claims: [
                'impersonator_user_id' => $actor->id,
                'impersonator_name' => $actor->name,
            ]),
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/setup/users/stop-impersonating",
     *     summary="Api Setup Users Impersonate Stop",
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
    public function stop(Request $request): JsonResponse
    {
        $payload = $request->attributes->get('jwt_payload', []);
        $impersonatorId = $payload['impersonator_user_id'] ?? null;

        if (! $impersonatorId) {
            return response()->json(['message' => 'No impersonation session is active.']);
        }

        $impersonator = User::query()->findOrFail($impersonatorId);
        $expiresAt = $this->jwt->defaultExpiry();

        $this->jwt->revoke($request->bearerToken());

        return response()->json([
            'message' => "Returned to {$impersonator->name}.",
            'token_type' => 'Bearer',
            'token' => $this->jwt->issue($impersonator, $expiresAt),
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

}

