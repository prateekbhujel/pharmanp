<?php

namespace App\Core\Support;

use Illuminate\Support\Str;

class AssetUrl
{
    public static function resolve(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://', '//', 'data:'])) {
            return $value;
        }

        $normalized = '/'.ltrim($value, '/');
        $basePath = request()->getBaseUrl();

        return ($basePath === '/' ? '' : rtrim($basePath, '/')).$normalized;
    }

    public static function publicStorage(string $path): string
    {
        return 'storage/'.ltrim($path, '/');
    }
}
