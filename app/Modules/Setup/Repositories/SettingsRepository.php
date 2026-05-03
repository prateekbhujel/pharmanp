<?php

namespace App\Modules\Setup\Repositories;

use App\Models\Setting;
use App\Modules\Setup\Repositories\Interfaces\SettingsRepositoryInterface;

class SettingsRepository implements SettingsRepositoryInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::getValue($key, $default);
    }

    public function has(string $key): bool
    {
        return Setting::hasValue($key);
    }

    public function put(string $key, mixed $value): void
    {
        Setting::putValue($key, $value);
    }

    public function putSecret(string $key, string $value): void
    {
        Setting::putSecretValue($key, $value);
    }
}
