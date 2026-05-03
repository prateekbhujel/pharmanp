<?php

namespace App\Console\Commands;

use App\Core\Modules\ModuleStructureInspector;
use App\Core\OpenApi\OpenApiAnnotationInspector;
use Illuminate\Console\Command;

class PharmaNpModuleDoctorCommand extends Command
{
    protected $signature = 'pharmanp:module-doctor
        {--json : Output machine-readable JSON}
        {--openapi : Include PIS-style OpenAPI annotation coverage}';

    protected $description = 'Validate PharmaNP module structure, routes, repository contracts, service-provider bindings and OpenAPI comments.';

    public function handle(ModuleStructureInspector $inspector, OpenApiAnnotationInspector $openApi): int
    {
        $rows = $inspector->inspect();
        $openApiRows = $this->option('openapi') ? $openApi->inspect() : [];

        if ($this->option('json')) {
            $payload = $this->option('openapi')
                ? ['modules' => $rows, 'openapi' => $openApiRows]
                : $rows;

            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->hasFailures($inspector, $openApi) ? self::FAILURE : self::SUCCESS;
        }

        $this->components->info('PharmaNP module architecture check');
        $this->table(
            ['Module', 'Status', 'Repository Contracts', 'Missing', 'Warnings'],
            collect($rows)->map(fn (array $row): array => [
                $row['name'],
                $row['status'],
                count($row['repository_interfaces']),
                implode(', ', $row['missing']) ?: '-',
                implode(', ', $row['warnings']) ?: '-',
            ])->all(),
        );

        if ($this->option('openapi')) {
            $this->newLine();
            $this->components->info('PIS-style OpenAPI annotation check');
            $this->table(
                ['Type', 'Class', 'Status', 'Missing'],
                collect($openApiRows)->map(fn (array $row): array => [
                    $row['type'],
                    $row['class'],
                    $row['status'],
                    implode(', ', $row['missing']) ?: '-',
                ])->all(),
            );
        }

        if ($this->hasFailures($inspector, $openApi)) {
            $this->components->error('One or more modules do not satisfy the PharmaNP modular contract.');

            return self::FAILURE;
        }

        $this->components->info('All configured modules satisfy the modular contract.');

        return self::SUCCESS;
    }

    private function hasFailures(ModuleStructureInspector $inspector, OpenApiAnnotationInspector $openApi): bool
    {
        return $inspector->hasFailures()
            || ($this->option('openapi') && $openApi->hasFailures());
    }
}
