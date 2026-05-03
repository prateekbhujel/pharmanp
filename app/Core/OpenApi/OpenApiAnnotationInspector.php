<?php

namespace App\Core\OpenApi;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final readonly class OpenApiAnnotationInspector
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function inspect(): array
    {
        return collect(File::allFiles(app_path('Modules')))
            ->filter(fn ($file): bool => $file->getExtension() === 'php')
            ->map(fn ($file): string => $file->getPathname())
            ->filter(fn (string $path): bool => $this->isDocumentable($path))
            ->map(fn (string $path): array => $this->inspectFile($path))
            ->values()
            ->all();
    }

    public function hasFailures(): bool
    {
        return collect($this->inspect())->contains(
            fn (array $row): bool => $row['status'] === 'failed'
        );
    }

    private function inspectFile(string $path): array
    {
        $source = (string) file_get_contents($path);
        $type = $this->type($path);
        $class = $this->className($path);
        $missing = match ($type) {
            'controller' => $this->controllerMissing($source, $class),
            'request' => $this->requestMissing($source),
            'resource' => $this->resourceMissing($source),
            default => [],
        };

        return [
            'type' => $type,
            'class' => $class,
            'path' => $path,
            'status' => $missing === [] ? 'passed' : 'failed',
            'missing' => $missing,
        ];
    }

    private function isDocumentable(string $path): bool
    {
        return Str::contains($path, [
            '/Http/Controllers/',
            '/Http/Requests/',
            '/Http/Resources/',
        ]) && ! Str::endsWith($path, [
            'OpenApiController.php',
        ]);
    }

    private function type(string $path): string
    {
        return match (true) {
            Str::contains($path, '/Http/Controllers/') => 'controller',
            Str::contains($path, '/Http/Requests/') => 'request',
            Str::contains($path, '/Http/Resources/') => 'resource',
            default => 'unknown',
        };
    }

    /**
     * @return list<string>
     */
    private function controllerMissing(string $source, string $class): array
    {
        $missing = [];

        if (! str_contains($source, '@OA\\Tag')) {
            $missing[] = '@OA\\Tag';
        }

        foreach ($this->routedMethods($class) as $method) {
            if (! $this->methodHasOperation($source, $method)) {
                $missing[] = '@OA operation for '.$method.'()';
            }
        }

        if (! str_contains($source, 'ModularController')) {
            $missing[] = 'ModularController';
        }

        return $missing;
    }

    /**
     * @return list<string>
     */
    private function routedMethods(string $class): array
    {
        $methods = [];

        foreach (Route::getRoutes() as $route) {
            if (! $this->isApiRoute($route->uri())) {
                continue;
            }

            $action = $route->getActionName();
            $method = '__invoke';

            if (str_contains($action, '@')) {
                [$routeClass, $method] = explode('@', $action, 2);
            } else {
                $routeClass = $action;
            }

            if ($routeClass === $class) {
                $methods[] = $method;
            }
        }

        return array_values(array_unique($methods));
    }

    private function isApiRoute(string $uri): bool
    {
        $prefix = trim((string) config('pharmanp-modules.api_prefix', 'api/v1'), '/');

        return $uri === $prefix || str_starts_with($uri, $prefix.'/');
    }

    private function methodHasOperation(string $source, string $method): bool
    {
        $pattern = '/public\s+function\s+'.preg_quote($method, '/').'\s*\(/';

        if (! preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        $position = (int) $match[0][1];
        $lookback = substr($source, max(0, $position - 1000), 1000);

        return (bool) preg_match('/@OA\\\\(Get|Post|Put|Patch|Delete)\s*\(/', $lookback);
    }

    /**
     * @return list<string>
     */
    private function requestMissing(string $source): array
    {
        return str_contains($source, '@OA\\Schema') ? [] : ['@OA\\Schema'];
    }

    /**
     * @return list<string>
     */
    private function resourceMissing(string $source): array
    {
        return str_contains($source, '@OA\\Schema') ? [] : ['@OA\\Schema'];
    }

    private function className(string $path): string
    {
        $relative = Str::of($path)
            ->after(app_path().DIRECTORY_SEPARATOR)
            ->replace(DIRECTORY_SEPARATOR, '\\')
            ->beforeLast('.php')
            ->toString();

        return 'App\\'.$relative;
    }
}
