<?php

namespace App\Modules\Setup\Services;

use App\Core\Support\AssetUrl;
use App\Core\Support\ProductMeta;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BrandingSettingsService
{
    /**
     * Return the resolved branding payload (asset URLs resolved to public URLs).
     */
    public function brandingPayload(): array
    {
        $branding = $this->storedBranding();

        foreach (['logo_url', 'sidebar_logo_url', 'app_icon_url', 'favicon_url'] as $key) {
            $branding[$key] = AssetUrl::resolve($branding[$key] ?? null);
        }

        $branding['product'] = ProductMeta::payload();

        return $branding;
    }

    /**
     * Merge validated data and any uploaded files into the persisted branding setting.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, UploadedFile|null>  $files  Keys: logo_file, sidebar_logo_file, app_icon_file, favicon_file
     */
    public function updateBranding(array $validated, array $files = []): array
    {
        $current = $this->storedBranding();

        $payload = [
            ...$current,
            ...Arr::except($validated, ['logo_file', 'sidebar_logo_file', 'app_icon_file', 'favicon_file']),
            'layout' => 'vertical',
            'sidebar_default_collapsed' => array_key_exists('sidebar_default_collapsed', $validated)
                ? (bool) $validated['sidebar_default_collapsed']
                : (bool) ($current['sidebar_default_collapsed'] ?? true),
            'show_breadcrumbs' => array_key_exists('show_breadcrumbs', $validated)
                ? (bool) $validated['show_breadcrumbs']
                : (bool) ($current['show_breadcrumbs'] ?? true),
        ];

        foreach ([
            'logo_file' => 'logo_url',
            'sidebar_logo_file' => 'sidebar_logo_url',
            'app_icon_file' => 'app_icon_url',
            'favicon_file' => 'favicon_url',
        ] as $fileKey => $settingKey) {
            if (isset($files[$fileKey]) && $files[$fileKey] instanceof UploadedFile) {
                $payload[$settingKey] = $this->storeBrandAsset($files[$fileKey]);
            }
        }

        Setting::putValue('app.branding', $payload);

        return $this->brandingPayload();
    }

    private function storedBranding(): array
    {
        $branding = Setting::getValue('app.branding', [
            'app_name' => config('app.name', 'PharmaNP'),
            'logo_url' => null,
            'sidebar_logo_url' => null,
            'app_icon_url' => null,
            'favicon_url' => null,
            'accent_color' => '#0f766e',
            'layout' => 'vertical',
            'sidebar_default_collapsed' => true,
            'show_breadcrumbs' => true,
            'country_code' => 'NP',
            'currency_symbol' => 'Rs.',
            'calendar_type' => 'bs',
        ]);

        foreach (['logo_url', 'sidebar_logo_url', 'app_icon_url', 'favicon_url'] as $key) {
            $branding[$key] = $this->normalizeStoredAssetPath($branding[$key] ?? null);
        }

        return $branding;
    }

    private function normalizeStoredAssetPath(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH) ?: null;

        if ($path && Str::startsWith($path, '/storage/')) {
            return ltrim($path, '/');
        }

        return $value;
    }

    private function storeBrandAsset(UploadedFile $file): string
    {
        return AssetUrl::publicStorage($file->store('settings/branding', 'public'));
    }
}
