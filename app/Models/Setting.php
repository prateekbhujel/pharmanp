<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
