<?php

namespace App\Modules\Core\OpenApi;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\Modules\ModuleDefinition;
use App\Core\Modules\ModuleRegistry;
use App\Core\Support\ProductMeta;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final readonly class OpenApiSpecBuilder
{
    private const HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    public function __construct(private ModuleRegistry $modules) {}

    public function build(Request $request): array
    {
        $spec = $this->baseSpec();
        $spec['paths'] = array_replace_recursive($this->discoveredPaths(), $spec['paths'] ?? []);
        $spec['info']['version'] = ProductMeta::version();
        $spec['servers'] = [$this->server($request)];

        return $this->withNormalizedTags($spec);
    }

    private function baseSpec(): array
    {
        $path = app_path('Modules/Core/OpenApi/pharmanp.v1.json');

        abort_unless(is_file($path), 404);

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function server(Request $request): array
    {
        return [
            'url' => rtrim($request->getSchemeAndHttpHost(), '/').'/'.$this->apiPrefix(),
            'description' => config('app.name', 'PharmaNP').' API Server',
        ];
    }

    private function discoveredPaths(): array
    {
        $paths = [];

        foreach (Route::getRoutes() as $route) {
            if (! $this->shouldDocument($route)) {
                continue;
            }

            $path = $this->openApiPath($route->uri());
            $methods = array_values(array_diff($route->methods(), ['HEAD']));

            foreach ($methods as $method) {
                $paths[$path][strtolower($method)] = $this->operation($route, $method);
            }
        }

        ksort($paths);

        return $paths;
    }

    private function operation(LaravelRoute $route, string $method): array
    {
        $uri = $route->uri();
        $writeMethod = in_array($method, ['POST', 'PUT', 'PATCH'], true);
        $parameters = [
            ...$this->pathParameters($uri),
            ...$this->queryParameters($method, $uri),
        ];
        $operation = [
            'tags' => [$this->tagFor($uri)],
            'summary' => $this->summary($route),
            'operationId' => $this->operationId($route, $method),
            'parameters' => $parameters,
            'responses' => [
                '200' => [
                    'description' => 'Successful response',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponseEnvelope'],
                            'examples' => [
                                'success' => [
                                    'summary' => 'Standard success response',
                                    'value' => [
                                        'status' => 'success',
                                        'code' => 200,
                                        'message' => 'Records retrieved successfully.',
                                        'data' => [
                                            [
                                                'id' => 1,
                                                'name' => 'Kathmandu Care Pharmacy',
                                                'created_at' => '2026-05-04T10:15:00.000000Z',
                                                'updated_at' => '2026-05-04T10:15:00.000000Z',
                                            ],
                                        ],
                                    ],
                                ],
                                'paginated' => [
                                    'summary' => 'Paginated list response',
                                    'value' => [
                                        'status' => 'success',
                                        'code' => 200,
                                        'message' => 'Records retrieved successfully.',
                                        'data' => [
                                            ['id' => 1, 'name' => 'Paracetamol 500'],
                                        ],
                                        'links' => [
                                            'first' => 'https://pharmanp.example.test/api/v1/inventory/products?page=1',
                                            'last' => 'https://pharmanp.example.test/api/v1/inventory/products?page=10',
                                            'prev' => null,
                                            'next' => 'https://pharmanp.example.test/api/v1/inventory/products?page=2',
                                        ],
                                        'meta' => [
                                            'current_page' => 1,
                                            'from' => 1,
                                            'last_page' => 10,
                                            'path' => 'https://pharmanp.example.test/api/v1/inventory/products',
                                            'per_page' => 15,
                                            'to' => 15,
                                            'total' => 150,
                                        ],
                                    ],
                                ],
                            ],
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

        if (! str_contains($uri, '/auth/login')) {
            $operation['security'] = [['bearerAuth' => []]];
        }

        return $operation;
    }

    private function withNormalizedTags(array $spec): array
    {
        $tags = $this->moduleTags();

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

    private function moduleTags(): array
    {
        return $this->modules->all()
            ->mapWithKeys(fn (ModuleDefinition $module): array => [
                strtoupper(str_replace('_', ' ', $module->key)).' - '.$module->name => [
                    'name' => strtoupper(str_replace('_', ' ', $module->key)).' - '.$module->name,
                    'description' => $module->domain,
                ],
            ])
            ->all();
    }

    private function shouldDocument(LaravelRoute $route): bool
    {
        $uri = $route->uri();
        $prefix = $this->apiPrefix();

        return str_starts_with($uri, $prefix)
            && ! str_ends_with($uri, 'openapi.json')
            && ! str_contains($uri, 'docs/api-docs.json');
    }

    private function openApiPath(string $uri): string
    {
        $path = '/'.ltrim(Str::after($uri, $this->apiPrefix()), '/');

        return $path === '/' ? '/' : $path;
    }

    private function apiPrefix(): string
    {
        return trim(config('pharmanp-modules.api_prefix', 'api/v1'), '/');
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

    private function segmentsFor(string $uri): array
    {
        $path = trim($uri, '/');
        $prefix = $this->apiPrefix().'/';

        if (str_starts_with($path, $prefix)) {
            $path = Str::after($path, $prefix);
        }

        return array_values(array_filter(explode('/', $path), fn (string $segment): bool => $segment !== ''));
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
            'INVENTORY' => 'API endpoints for product, batch, stock, expiry, and inventory master workflows.',
            'PURCHASE' => 'API endpoints for purchase bills, orders, returns, expiry returns, and supplier settlement.',
            'SALES' => 'API endpoints for sales bills, POS, returns, expiry returns, and payment collection.',
            'ACCOUNTING' => 'API endpoints for vouchers, payments, expenses, ledgers, aging, and accounting summaries.',
            'REPORTS' => 'API endpoints for server-side filtered operational and financial reporting.',
            default => "API endpoints for {$description} management.",
        };
    }

    private function pathParameters(string $uri): array
    {
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $name): array => [
                'name' => trim($name, '?'),
                'in' => 'path',
                'required' => ! str_ends_with($name, '?'),
                'schema' => ['type' => 'string'],
            ])
            ->values()
            ->all();
    }

    private function queryParameters(string $method, string $uri): array
    {
        if ($method !== 'GET') {
            return [];
        }

        $parameters = [
            $this->queryParameter('page', 'integer', 'Page number for server-side pagination.'),
            $this->queryParameter('per_page', 'integer', 'Rows per page. Defaults to 15 and is capped by the API.'),
            $this->queryParameter('search', 'string', 'Search keyword applied by the module repository.'),
            $this->queryParameter('sort_by', 'string', 'Sortable column key.'),
            $this->queryParameter('sort_order', 'string', 'Sort direction.', ['asc', 'desc']),
        ];

        if (str_contains($uri, 'reports') || str_contains($uri, 'dashboard') || str_contains($uri, 'movements')) {
            $parameters[] = $this->queryParameter('from', 'string', 'Start date in AD format or accepted PharmaNP date input.');
            $parameters[] = $this->queryParameter('to', 'string', 'End date in AD format or accepted PharmaNP date input.');
        }

        return $parameters;
    }

    private function queryParameter(string $name, string $type, string $description, ?array $enum = null): array
    {
        $schema = ['type' => $type];

        if ($enum !== null) {
            $schema['enum'] = $enum;
        }

        return [
            'name' => $name,
            'in' => 'query',
            'required' => false,
            'description' => $description,
            'schema' => $schema,
        ];
    }

    private function summary(LaravelRoute $route): string
    {
        return Str::headline((string) ($route->getName() ?: $route->uri()));
    }

    private function operationId(LaravelRoute $route, string $method): string
    {
        $source = (string) ($route->getName() ?: $method.'_'.$route->uri());

        return Str::camel(str_replace(['api.', '.', '-', '/', '{', '}'], ['api_', '_', '_', '_', '', ''], $source));
    }
}
