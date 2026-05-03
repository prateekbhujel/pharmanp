<?php

namespace App\Core\Modules;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final readonly class ModuleStructureInspector
{
    private const REQUIRED_DIRECTORIES = [
        'DTOs',
        'Providers',
        'Repositories',
        'Repositories/Interfaces',
        'Routes',
        'Services',
    ];

    private const HTTP_DIRECTORIES = [
        'Http/Controllers',
        'Http/Requests',
        'Http/Resources',
    ];

    public function __construct(
        private Application $app,
        private ModuleRegistry $modules,
    ) {}

    public function inspect(): array
    {
        return $this->modules->all()
            ->map(fn (ModuleDefinition $module) => $this->inspectModule($module))
            ->values()
            ->all();
    }

    public function hasFailures(): bool
    {
        return collect($this->inspect())->contains(
            fn (array $row): bool => $row['status'] === 'failed'
        );
    }

    private function inspectModule(ModuleDefinition $module): array
    {
        $path = $this->modulePath($module);
        $missing = [];
        $warnings = [];

        if (! File::isDirectory($path)) {
            return [
                'module' => $module->key,
                'name' => $module->name,
                'path' => $path,
                'status' => 'failed',
                'missing' => ['module directory'],
                'warnings' => [],
                'repository_interfaces' => [],
                'unbound_interfaces' => [],
            ];
        }

        foreach (self::REQUIRED_DIRECTORIES as $directory) {
            if (! File::isDirectory($path.'/'.$directory)) {
                $missing[] = $directory;
            }
        }

        if (! File::isFile($path.'/Routes/api.php')) {
            $missing[] = 'Routes/api.php';
        }

        if ($module->provider && ! class_exists($module->provider)) {
            $missing[] = $module->provider;
        }

        foreach (self::HTTP_DIRECTORIES as $directory) {
            if (! File::isDirectory($path.'/'.$directory)) {
                $warnings[] = $directory.' missing; add it before exposing HTTP endpoints from this module';
            }
        }

        $interfaces = $this->repositoryInterfaces($module, $path);
        $unbound = collect($interfaces)
            ->filter(fn (string $interface): bool => ! $this->app->bound($interface))
            ->values()
            ->all();

        if ($interfaces === []) {
            $missing[] = 'repository interface';
        }

        if ($unbound !== []) {
            $missing[] = 'repository bindings';
        }

        return [
            'module' => $module->key,
            'name' => $module->name,
            'path' => $path,
            'status' => $missing === [] ? 'passed' : 'failed',
            'missing' => $missing,
            'warnings' => $warnings,
            'repository_interfaces' => $interfaces,
            'unbound_interfaces' => $unbound,
        ];
    }

    private function modulePath(ModuleDefinition $module): string
    {
        $relative = Str::after($module->namespace, 'App\\');

        return app_path(str_replace('\\', DIRECTORY_SEPARATOR, $relative));
    }

    /**
     * @return list<class-string>
     */
    private function repositoryInterfaces(ModuleDefinition $module, string $path): array
    {
        $interfaces = [];

        foreach (File::glob($path.'/Repositories/Interfaces/*Interface.php') ?: [] as $file) {
            $class = $module->namespace.'\\Repositories\\Interfaces\\'.basename($file, '.php');

            if (interface_exists($class)) {
                $interfaces[] = $class;
            }
        }

        sort($interfaces);

        return $interfaces;
    }
}
