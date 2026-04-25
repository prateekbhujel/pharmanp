<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PharmaNpBackupCommand extends Command
{
    protected $signature = 'pharmanp:backup {--path= : Optional backup directory}';

    protected $description = 'Create a backup placeholder for PharmaNP database/files before updates.';

    public function handle(): int
    {
        $path = $this->option('path') ?: storage_path('app/backups/'.now()->format('Ymd_His'));
        File::ensureDirectoryExists($path);

        File::put($path.'/README.txt', "PharmaNP backup directory created at ".now()->toDateTimeString().PHP_EOL);

        $this->info('Backup directory prepared: '.$path);
        $this->warn('Configure database dump and public/storage copy for the deployment environment before production updates.');

        return self::SUCCESS;
    }
}
