<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'is_private',
        'company_id',
        'store_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_private' => 'boolean',
        ];
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function putValue(string $key, mixed $value, bool $private = false): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'is_private' => $private],
        );
    }

    public static function getSecretValue(string $key, mixed $default = null): mixed
    {
        $value = static::getValue($key);

        if (is_array($value) && ($value['_encrypted'] ?? false) && isset($value['value'])) {
            try {
                return Crypt::decryptString((string) $value['value']);
            } catch (\Throwable) {
                return $default;
            }
        }

        return $value ?? $default;
    }

    public static function putSecretValue(string $key, string $value): self
    {
        return static::putValue($key, [
            '_encrypted' => true,
            'value' => Crypt::encryptString($value),
        ], true);
    }

    public static function hasValue(string $key): bool
    {
        $value = static::getValue($key);

        if (is_array($value) && ($value['_encrypted'] ?? false)) {
            return ! empty($value['value']);
        }

        return $value !== null && $value !== '';
    }
}
