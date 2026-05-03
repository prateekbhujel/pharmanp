<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Models\User;
use App\Modules\Core\Http\Requests\ApiLoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="CORE - Platform",
 *     description="API endpoints for CORE - Platform"
 * )
 */
class ApiAuthController extends ModularController
{
    /**
     * @OA\Post(
     *     path="/auth/login",
     *     summary="Api Auth Login",
     *     tags={"AUTH - Authentication"},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function login(ApiLoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $key = Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 6)) {
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $user = User::query()
            ->where('email', $credentials['email'])
            ->where('is_active', true)
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => 'The provided credentials are not valid.',
            ]);
        }

        RateLimiter::clear($key);

        if ($request->hasSession()) {
            Auth::guard('web')->login($user, (bool) ($credentials['remember'] ?? false));
            $request->session()->regenerate();
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = null;
        if ((bool) ($credentials['issue_token'] ?? false)) {
            $token = $user->createToken($credentials['device_name'] ?? 'PharmaNP Frontend')->plainTextToken;
        }

        return response()->json([
            'message' => 'Authenticated.',
            'token' => $token,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     summary="Api Auth Logout",
     *     tags={"AUTH - Authentication"},
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
    public function logout(Request $request): JsonResponse
    {
        if ($request->user()?->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logged out.']);
    }
}
