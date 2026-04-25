<?php

namespace App\Http\Middleware;

use App\Core\Services\InstallationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePharmaNpIsInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(InstallationService::class)->installed()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'PharmaNP setup is not completed.',
                ], 423);
            }

            return redirect()->route('setup.show');
        }

        return $next($request);
    }
}
