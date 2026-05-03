<?php

namespace App\Modules\Base\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

abstract class BaseModuleServiceProvider extends ServiceProvider
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
        $this->loadModuleRoutes($this->modulePath());
        $this->bootModule();
    }

    protected function bootModule(): void
    {
        //
    }

    protected function loadModuleRoutes(string $modulePath): void
    {
        $apiPath = $modulePath.'/Routes/api.php';

        if (is_file($apiPath)) {
            Route::middleware(['web', 'installed', 'pharmanp.api'])
                ->prefix(config('pharmanp-modules.api_prefix', 'api/v1'))
                ->as('api.')
                ->group($apiPath);
        }

        $webPath = $modulePath.'/Routes/web.php';

        if (is_file($webPath)) {
            $this->loadRoutesFrom($webPath);
        }
    }

    protected function modulePath(): string
    {
        return dirname(dirname((new ReflectionClass($this))->getFileName()));
    }
}
