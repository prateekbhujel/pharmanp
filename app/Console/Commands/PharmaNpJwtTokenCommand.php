<?php

namespace App\Console\Commands;

use App\Core\Services\JwtTokenService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class PharmaNpJwtTokenCommand extends Command
{
    protected $signature = 'pharmanp:jwt-token
        {email : Existing user email}
        {--days=1 : Expiry in days. Use 0 for configured minute TTL}';

    protected $description = 'Issue a signed JWT bearer token for Swagger, frontend, or mobile API testing.';

    public function handle(JwtTokenService $jwt): int
    {
        $user = User::query()->where('email', (string) $this->argument('email'))->first();

        if (! $user) {
            $this->components->error('User not found.');

            return self::FAILURE;
        }

        $days = max((int) $this->option('days'), 0);
        $expiresAt = $days > 0 ? CarbonImmutable::now()->addDays($days) : null;

        $this->components->info('JWT created. Store it like a password; do not commit it.');
        $this->line('User: '.$user->email);
        $this->line('Expires: '.($expiresAt?->toDateTimeString() ?: 'Configured TTL'));
        $this->newLine();
        $this->line($jwt->issue($user, $expiresAt));

        return self::SUCCESS;
    }
}
