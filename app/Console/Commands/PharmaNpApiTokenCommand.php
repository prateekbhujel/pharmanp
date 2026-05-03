<?php

namespace App\Console\Commands;

use App\Core\Services\ApiTokenService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class PharmaNpApiTokenCommand extends Command
{
    protected $signature = 'pharmanp:api-token
        {email : Existing user email}
        {--name=Swagger : Token label}
        {--days=30 : Expiry in days. Use 0 for no expiry}';

    protected $description = 'Issue a one-time visible API bearer token for Swagger, mobile, or external frontend testing.';

    public function handle(ApiTokenService $tokens): int
    {
        $user = User::query()->where('email', (string) $this->argument('email'))->first();

        if (! $user) {
            $this->components->error('User not found.');

            return self::FAILURE;
        }

        $days = max((int) $this->option('days'), 0);
        $expiresAt = $days > 0 ? CarbonImmutable::now()->addDays($days) : null;

        $issued = $tokens->create(
            user: $user,
            name: (string) $this->option('name'),
            expiresAt: $expiresAt,
            createdBy: $user,
        );

        $this->components->info('API token created. Copy it now; the plain token is not stored.');
        $this->line('User: '.$user->email);
        $this->line('Name: '.$issued['token']->name);
        $this->line('Expires: '.($expiresAt?->toDateTimeString() ?: 'Never'));
        $this->newLine();
        $this->line($issued['plain_text_token']);

        return self::SUCCESS;
    }
}
