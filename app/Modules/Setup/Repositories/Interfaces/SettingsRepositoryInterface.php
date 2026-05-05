<?php

namespace App\Modules\Setup\Repositories\Interfaces;

interface SettingsRepositoryInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;

    public function put(string $key, mixed $value): void;

    public function putSecret(string $key, string $value): void;
}
