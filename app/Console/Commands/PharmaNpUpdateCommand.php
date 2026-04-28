<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PharmaNpUpdateCommand extends Command
{
    protected $signature = 'pharmanp:update {--dry-run : Show the update steps without running them}';

    protected $description = 'Run the safe PharmaNP update checklist from CLI only.';

    public function handle(): int
    {
        $this->info('PharmaNP update checklist');
        $this->line('1. Run php artisan pharmanp:backup and verify backup files.');
        $this->line('2. Put the app in maintenance mode if this is production.');
        $this->line('3. Deploy reviewed code through the hosting-safe release process.');
        $this->line('4. Run composer install --no-dev --optimize-autoloader when applicable.');
        $this->line('5. Run php artisan migrate --force after backup verification.');
        $this->line('6. Run php artisan optimize:clear && php artisan optimize.');
        $this->line('7. Build frontend assets before upload or on the server where Node is available.');

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $this->warn('This command is intentionally non-destructive.');

        return self::SUCCESS;
    }
}
