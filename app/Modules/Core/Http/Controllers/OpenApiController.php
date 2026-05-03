<?php

namespace App\Modules\Core\Http\Controllers;

use App\Core\Support\ProductMeta;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class OpenApiController extends Controller
{
    private const HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    public function __invoke(Request $request): JsonResponse
    {
        $path = app_path('Modules/Core/OpenApi/pharmanp.v1.json');

        abort_unless(is_file($path), 404);

        $spec = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $spec['paths'] = array_replace_recursive($this->discoveredPaths(), $spec['paths'] ?? []);
        $spec['info']['version'] = ProductMeta::version();
        $spec['servers'] = [
            [
                'url' => rtrim($request->getSchemeAndHttpHost(), '/').'/'.trim(config('pharmanp-modules.api_prefix', 'api/v1'), '/'),
                'description' => config('app.name', 'PharmaNP').' API Server',
            ],
        ];
        $spec = $this->normalizeTags($spec);

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

            if (str_ends_with($uri, 'openapi.json')) {
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
        $segments = $this->segmentsFor($uri);
        $module = $segments[0] ?? 'core';
        $resource = $segments[1] ?? null;

        return match ($module) {
            'auth' => 'AUTH - Authentication',
            'dashboard' => 'DASHBOARD - Overview',
            'inventory' => 'INVENTORY - '.$this->resourceLabel($resource ?? 'stock'),
            'purchase' => 'PURCHASE - '.$this->resourceLabel($resource ?? 'purchase'),
            'purchases' => 'PURCHASE - Purchase Bills',
            'sales' => 'SALES - '.$this->resourceLabel($resource ?? 'sales'),
            'accounting' => 'ACCOUNTING - '.$this->resourceLabel($resource ?? 'accounts'),
            'mr' => 'FIELD FORCE - '.$this->resourceLabel($resource ?? 'performance'),
            'reports' => 'REPORTS - Operational Reports',
            'imports' => 'IMPORT EXPORT - '.$this->resourceLabel($resource ?? 'imports'),
            'exports' => 'EXPORTS - Downloads',
            'settings' => 'SETUP - '.$this->resourceLabel($resource ?? 'settings'),
            'setup' => 'SETUP - '.$this->resourceLabel($resource ?? 'setup'),
            'profile' => 'SETUP - Profile',
            'suppliers' => 'PARTY - Suppliers',
            'customers' => 'PARTY - Customers',
            'me' => 'CORE - Current User',
            'modules' => 'CORE - Module Catalog',
            'search' => 'CORE - Global Search',
            default => 'CORE - Platform',
        };
    }

    private function normalizeTags(array $spec): array
    {
        $tags = [];

        foreach (($spec['paths'] ?? []) as $path => $operations) {
            foreach (self::HTTP_METHODS as $method) {
                if (! isset($operations[$method])) {
                    continue;
                }

                $tag = $this->tagFor($path);
                $spec['paths'][$path][$method]['tags'] = [$tag];
                $tags[$tag] = [
                    'name' => $tag,
                    'description' => $this->tagDescription($tag),
                ];
            }
        }

        ksort($tags);
        $spec['tags'] = array_values($tags);

        return $spec;
    }

    private function segmentsFor(string $uri): array
    {
        $path = trim($uri, '/');

        if (str_starts_with($path, 'api/v1/')) {
            $path = Str::after($path, 'api/v1/');
        }

        return array_values(array_filter(explode('/', $path), fn (string $segment) => $segment !== ''));
    }

    private function resourceLabel(string $resource): string
    {
        return match ($resource) {
            'stock-adjustments' => 'Stock Adjustments',
            'stock-movements' => 'Stock Movements',
            'product-lookup' => 'Product Lookup',
            'dropdown-options' => 'Dropdown Options',
            'fiscal-years' => 'Fiscal Years',
            'party-types' => 'Customer Types',
            'supplier-types' => 'Supplier Types',
            'draft-purchase' => 'OCR Draft Purchase',
            default => Str::headline(str_replace('-', ' ', $resource)),
        };
    }

    private function tagDescription(string $tag): string
    {
        [$module, $resource] = array_pad(explode(' - ', $tag, 2), 2, 'Platform');
        $description = Str::lower($resource);

        return match ($module) {
            'AUTH' => 'API endpoints for authentication, logout, and token-backed Swagger or frontend testing.',
            'DASHBOARD' => 'API endpoints for dashboard metrics, KPIs, alerts, and operational summaries.',
            'CORE' => 'API endpoints for shared platform services including current user, modules, and search.',
            default => "API endpoints for {$description} management.",
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
