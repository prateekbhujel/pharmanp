<?php

namespace App\Core\Modules;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [];
    }

    public function register(): void
    {
        foreach ($this->bindings() as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }

    public function boot(): void
    {
        $this->loadModuleApiRoutes();
        $this->bootModule();
    }

    protected function bootModule(): void
    {
        //
    }

    protected function loadModuleApiRoutes(): void
    {
        $path = $this->modulePath().'/Routes/api.php';

        if (! is_file($path)) {
            return;
        }

        Route::middleware(['web', 'installed', 'pharmanp.api'])
            ->prefix(config('pharmanp-modules.api_prefix', 'api/v1'))
            ->as('api.')
            ->group($path);
    }

    protected function modulePath(): string
    {
        return dirname((new ReflectionClass($this))->getFileName());
    }
}
