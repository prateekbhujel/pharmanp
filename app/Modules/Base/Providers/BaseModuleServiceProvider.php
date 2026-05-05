<?php

namespace App\Modules\Base\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

abstract class BaseModuleServiceProvider extends ServiceProvider
{
    protected function loadModuleRoutes(string $modulePath): void
    {
        $apiPath = $modulePath.'/Routes/api.php';

        if (is_file($apiPath)) {
            Route::middleware(config('pharmanp-modules.api_middleware', ['api', 'installed', 'pharmanp.api']))
                ->prefix(config('pharmanp-modules.api_prefix', 'api/v1'))
                ->as('api.')
                ->group($apiPath);
        }

        $webPath = $modulePath.'/Routes/web.php';

        if (is_file($webPath)) {
            $this->loadRoutesFrom($webPath);
        }
    }
}
