<?php

namespace App\Core\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class InstallationService
{
    public const INSTALLED_KEY = 'app.installed';

    public function installed(): bool
    {
        if (File::exists(storage_path('app/installed'))) {
            return true;
        }

        return (bool) (Setting::getValue(self::INSTALLED_KEY, false));
    }

    public function status(): array
    {
        return [
            'installed' => $this->installed(),
            'environment' => [
                'php' => PHP_VERSION,
                'app_key' => filled(config('app.key')),
                'storage_writable' => is_writable(storage_path()),
                'cache_writable' => is_writable(storage_path('framework/cache')),
                'app_env' => app()->environment(),
            ],
            'database' => $this->databaseStatus(),
        ];
    }

    public function markInstalled(array $meta = []): void
    {
        Setting::putValue(self::INSTALLED_KEY, [
            'installed' => true,
            'installed_at' => now()->toISOString(),
            ...$meta,
        ], true);

        File::put(storage_path('app/installed'), now()->toISOString());
    }

    private function databaseStatus(): array
    {
        try {
            DB::select('select 1');

            return ['ok' => true, 'message' => 'Connected'];
        } catch (\Throwable $throwable) {
            return ['ok' => false, 'message' => $throwable->getMessage()];
        }
    }
}
