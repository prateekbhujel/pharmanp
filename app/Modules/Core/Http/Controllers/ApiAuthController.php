<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApiAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
            'issue_token' => ['nullable', 'boolean'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

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
