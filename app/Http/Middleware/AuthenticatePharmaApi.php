<?php

namespace App\Http\Middleware;

use App\Core\Services\ApiTokenService;
use App\Core\Services\JwtTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePharmaApi
{
    public function __construct(
        private readonly ApiTokenService $tokens,
        private readonly JwtTokenService $jwt,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken()) {
            $bearerToken = $request->bearerToken();
            $user = $this->jwt->looksLikeJwt($bearerToken)
                ? $this->jwt->userForToken($bearerToken)
                : $this->tokens->userForToken($bearerToken);

            if (! $user) {
                return response()->json(['message' => 'Invalid or expired API token.'], 401);
            }

            Auth::guard('web')->setUser($user);
            $request->setUserResolver(fn () => $user);

            return $next($request);
        }

        if (Auth::guard('web')->check()) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
