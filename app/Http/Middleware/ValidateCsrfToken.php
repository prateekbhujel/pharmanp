<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken as BaseValidateCsrfToken;
use Illuminate\Http\Request;

class ValidateCsrfToken extends BaseValidateCsrfToken
{
    public function handle($request, Closure $next)
    {
        if ($this->isBearerApiRequest($request)) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    private function isBearerApiRequest(Request $request): bool
    {
        return filled($request->bearerToken())
            && str_starts_with($request->path(), 'api/v1/');
    }
}
