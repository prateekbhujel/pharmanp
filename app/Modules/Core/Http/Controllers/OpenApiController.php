<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class OpenApiController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $path = app_path('Modules/Core/OpenApi/pharmanp.v1.json');

        abort_unless(is_file($path), 404);

        $spec = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $spec['paths'] = array_replace_recursive($this->discoveredPaths(), $spec['paths'] ?? []);

        return response()->json($spec);
    }

    private function discoveredPaths(): array
    {
        $paths = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/v1')) {
                continue;
            }

            $path = '/'.ltrim(Str::after($uri, 'api/v1'), '/');
            $path = $path === '/' ? '/' : $path;
            $methods = array_values(array_diff($route->methods(), ['HEAD']));

            foreach ($methods as $method) {
                $paths[$path][strtolower($method)] = $this->operation($route->getName(), $uri, $method);
            }
        }

        ksort($paths);

        return $paths;
    }

    private function operation(?string $routeName, string $uri, string $method): array
    {
        $writeMethod = in_array($method, ['POST', 'PUT', 'PATCH'], true);
        $operation = [
            'tags' => [$this->tagFor($uri)],
            'summary' => Str::headline((string) ($routeName ?: $uri)),
            'operationId' => Str::camel(str_replace(['api.', '.', '-', '/', '{', '}'], ['api_', '_', '_', '_', '', ''], (string) ($routeName ?: $method.'_'.$uri))),
            'parameters' => $this->pathParameters($uri),
            'responses' => [
                '200' => [
                    'description' => 'Successful response',
                    'content' => [
                        'application/json' => [
                            'schema' => ['type' => 'object'],
                        ],
                    ],
                ],
                '401' => ['$ref' => '#/components/responses/Unauthenticated'],
                '403' => ['$ref' => '#/components/responses/Forbidden'],
                '422' => ['$ref' => '#/components/responses/ValidationError'],
            ],
        ];

        if ($writeMethod) {
            $operation['requestBody'] = [
                'required' => false,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'additionalProperties' => true,
                        ],
                    ],
                ],
            ];
        }

        return $operation;
    }

    private function tagFor(string $uri): string
    {
        $segments = explode('/', $uri);
        $module = $segments[2] ?? 'core';

        return match ($module) {
            'inventory' => 'Inventory',
            'purchase', 'purchases' => 'Purchase',
            'sales' => 'Sales',
            'accounting' => 'Accounting',
            'mr' => 'Field Force',
            'reports' => 'Reports',
            'imports' => 'Import Export',
            'settings', 'setup', 'profile' => 'Setup',
            'suppliers', 'customers' => 'Party',
            'exports' => 'Exports',
            default => 'Core',
        };
    }

    private function pathParameters(string $uri): array
    {
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $name) => [
                'name' => trim($name, '?'),
                'in' => 'path',
                'required' => ! str_ends_with($name, '?'),
                'schema' => ['type' => 'string'],
            ])
            ->values()
            ->all();
    }
}
