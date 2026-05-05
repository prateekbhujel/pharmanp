<?php

namespace App\Http\Middleware;

use App\Core\Services\JwtTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePharmaApi
{
    public function __construct(
        private readonly JwtTokenService $jwt,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken() ?: $request->query('token');

        if (! $bearerToken) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $payload = $this->jwt->payload($bearerToken);
        $user = $this->jwt->userForToken($bearerToken);

        if (! $user) {
            return response()->json(['message' => 'Invalid or expired JWT token.'], 401);
        }

        Auth::guard('web')->setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('jwt_payload', $payload);

        return $next($request);
    }
}
