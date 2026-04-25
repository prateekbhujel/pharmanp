<?php

namespace App\Http\Middleware;

use App\Core\Services\InstallationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfPharmaNpIsInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app(InstallationService::class)->installed()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'PharmaNP is already installed.'], 409);
            }

            return redirect()->route('app');
        }

        return $next($request);
    }
}
