<?php

namespace App\Providers;

use App\Core\Modules\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

class PharmaNpModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $modules = config('pharmanp-modules.modules', []);

        $this->app->singleton(ModuleRegistry::class, fn () => new ModuleRegistry($modules));

        foreach ($modules as $module) {
            $provider = $module['provider'] ?? null;

            if (is_string($provider) && class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }
}
