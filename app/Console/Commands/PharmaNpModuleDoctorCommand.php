<?php

namespace App\Console\Commands;

use App\Core\Modules\ModuleStructureInspector;
use Illuminate\Console\Command;

class PharmaNpModuleDoctorCommand extends Command
{
    protected $signature = 'pharmanp:module-doctor {--json : Output machine-readable JSON}';

    protected $description = 'Validate PharmaNP module structure, routes, repository contracts and service-provider bindings.';

    public function handle(ModuleStructureInspector $inspector): int
    {
        $rows = $inspector->inspect();

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $inspector->hasFailures() ? self::FAILURE : self::SUCCESS;
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

        if ($inspector->hasFailures()) {
            $this->components->error('One or more modules do not satisfy the PharmaNP modular contract.');

            return self::FAILURE;
        }

        $this->components->info('All configured modules satisfy the modular contract.');

        return self::SUCCESS;
    }
}
