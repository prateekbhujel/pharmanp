<?php

namespace App\Providers;

use App\Core\Modules\ModuleRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class PharmaNpModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $modules = config('pharmanp-modules.modules', []);

        $this->app->singleton(ModuleRegistry::class, fn () => new ModuleRegistry($modules));
        $registered = [];

        foreach ($modules as $module) {
            $provider = $module['provider'] ?? null;

            if (is_string($provider) && class_exists($provider)) {
                $this->app->register($provider);
                $registered[$provider] = true;
            }
        }

        foreach ($this->discoverModuleProviders() as $provider) {
            if (! isset($registered[$provider])) {
                $this->app->register($provider);
            }
        }
    }

    /**
     * @return list<class-string>
     */
    private function discoverModuleProviders(): array
    {
        $providers = [];

        foreach (File::glob(app_path('Modules/*/Providers/*ServiceProvider.php')) ?: [] as $file) {
            $module = basename(dirname(dirname($file)));

            if ($module === 'Base') {
                continue;
            }

            $class = 'App\\Modules\\'.$module.'\\Providers\\'.basename($file, '.php');

            if (class_exists($class)) {
                $providers[] = $class;
            }
        }

        return $providers;
    }
}
