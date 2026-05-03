<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WrapApiResponse
{
    /**
     * Normalize API JSON into the response envelope used by PharmaNP modules.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof JsonResponse || ! $this->shouldWrap($request, $response)) {
            return $response;
        }

        $payload = $response->getData(true);

        if (! is_array($payload) || array_key_exists('openapi', $payload)) {
            return $response;
        }

        $statusCode = $response->getStatusCode();
        $success = $statusCode >= 200 && $statusCode < 300;
        $known = ['status', 'code', 'message', 'data', 'errors', 'links', 'meta'];

        $wrapped = [
            'status' => $payload['status'] ?? ($success ? 'success' : 'error'),
            'code' => $payload['code'] ?? $statusCode,
            'message' => $payload['message'] ?? $this->defaultMessage($statusCode, $success),
            'data' => array_key_exists('data', $payload) ? $payload['data'] : null,
        ];

        if (array_key_exists('links', $payload)) {
            $wrapped['links'] = $payload['links'];
        }

        if (array_key_exists('meta', $payload)) {
            $wrapped['meta'] = $payload['meta'];
        }

        foreach (array_diff_key($payload, array_flip($known)) as $key => $value) {
            $wrapped[$key] = $value;
        }

        if (array_key_exists('errors', $payload)) {
            $wrapped['errors'] = $payload['errors'];
        }

        $response->setData($wrapped);

        return $response;
    }

    private function shouldWrap(Request $request, JsonResponse $response): bool
    {
        if ($response->getStatusCode() === Response::HTTP_NO_CONTENT) {
            return false;
        }

        $prefix = trim((string) config('pharmanp-modules.api_prefix', 'api/v1'), '/');

        return $request->is($prefix) || $request->is($prefix.'/*');
    }

    private function defaultMessage(int $statusCode, bool $success): string
    {
        return Response::$statusTexts[$statusCode] ?? ($success ? 'OK' : 'Request failed');
    }
}
