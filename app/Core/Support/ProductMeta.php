<?php

namespace App\Core\Support;

use Illuminate\Support\Str;

class ProductMeta
{
    public static function payload(): array
    {
        $version = self::version();

        return [
            'name' => config('pharmanp.product.name', 'PharmaNP'),
            'developer_name' => config('pharmanp.product.developer_name', 'Pratik Bhujel'),
            'developer_email' => config('pharmanp.product.developer_email', 'prateekbhujelpb@gmail.com'),
            'repository' => config('pharmanp.product.repository'),
            'release_channel' => config('pharmanp.product.release_channel', 'Stable'),
            'version' => $version,
            'version_label' => self::versionLabel($version),
        ];
    }

    public static function version(): string
    {
        $configured = trim((string) config('pharmanp.version', ''));
        if ($configured !== '') {
            return $configured;
        }

        $tag = self::localGitTag();
        if ($tag !== null) {
            return $tag;
        }

        $versionFile = base_path('VERSION');
        if (is_file($versionFile)) {
            $version = trim((string) file_get_contents($versionFile));
            if ($version !== '') {
                return $version;
            }
        }

        return 'dev';
    }

    private static function versionLabel(string $version): string
    {
        if ($version === '' || $version === 'dev') {
            return 'dev';
        }

        return Str::startsWith($version, 'v') ? $version : 'v'.$version;
    }

    private static function localGitTag(): ?string
    {
        if (! function_exists('shell_exec') || ! is_dir(base_path('.git'))) {
            return null;
        }

        $command = 'git -C '.escapeshellarg(base_path()).' describe --tags --abbrev=0 2>/dev/null';
        $tag = trim((string) @shell_exec($command));

        return $tag !== '' ? $tag : null;
    }
}
