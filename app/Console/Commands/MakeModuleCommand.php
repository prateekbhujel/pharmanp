<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleCommand extends Command
{
    protected $signature = 'module:make {name : Module name, for example Targets or DoctorVisit} {--force : Overwrite generated files}';

    protected $description = 'Create a PharmaNP module with provider, routes, controller, DTO, resources, service and repository contracts.';

    private const DIRECTORIES = [
        'DTOs',
        'Http/Controllers',
        'Http/Requests',
        'Http/Resources',
        'Models',
        'Providers',
        'Repositories/Interfaces',
        'Repositories',
        'Routes',
        'Services',
    ];

    public function handle(): int
    {
        $module = Str::studly((string) $this->argument('name'));
        $path = app_path('Modules/'.$module);

        foreach (self::DIRECTORIES as $directory) {
            File::ensureDirectoryExists($path.'/'.$directory);
        }

        foreach ($this->files($module) as $file => $stub) {
            $target = $path.'/'.$file;

            if (File::exists($target) && ! $this->option('force')) {
                $this->components->warn('Skipped existing '.$this->relative($target));

                continue;
            }

            File::put($target, $this->render($stub, $module));
            $this->components->info('Created '.$this->relative($target));
        }

        $this->newLine();
        $this->line('Register the provider in config/pharmanp-modules.php when the module is part of the shipped product catalog:');
        $this->line("App\\Modules\\{$module}\\Providers\\{$module}ServiceProvider::class");

        return self::SUCCESS;
    }

    private function files(string $module): array
    {
        return [
            "DTOs/{$module}Data.php" => 'dto',
            "Http/Controllers/{$module}Controller.php" => 'controller',
            "Http/Requests/Store{$module}Request.php" => 'store-request',
            "Http/Requests/Update{$module}Request.php" => 'update-request',
            "Http/Resources/{$module}Resource.php" => 'resource',
            "Http/Resources/{$module}Collection.php" => 'collection',
            "Models/{$module}.php" => 'model',
            "Providers/{$module}ServiceProvider.php" => 'provider',
            "Repositories/Interfaces/{$module}RepositoryInterface.php" => 'repository-interface',
            "Repositories/{$module}Repository.php" => 'repository',
            'Routes/api.php' => 'routes',
            "Services/{$module}Service.php" => 'service',
        ];
    }

    private function render(string $stub, string $module): string
    {
        $content = File::get(base_path('stubs/module/'.$stub.'.stub'));
        $kebabPlural = Str::kebab(Str::pluralStudly($module));

        return str_replace(
            ['{{ module }}', '{{ modulePluralKebab }}', '{{ moduleVariable }}', '{{ moduleTitle }}'],
            [$module, $kebabPlural, Str::camel($module), Str::headline($module)],
            $content,
        );
    }

    private function relative(string $path): string
    {
        return Str::after($path, base_path().DIRECTORY_SEPARATOR);
    }
}
