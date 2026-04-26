<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Modules\Setup\Http\Requests\BrandingSettingsRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    public function show(): JsonResponse
    {
        abort_unless(request()->user()?->is_owner || request()->user()?->can('setup.manage'), 403);

        return response()->json(['data' => $this->branding()]);
    }

    public function update(BrandingSettingsRequest $request): JsonResponse
    {
        $current = $this->branding();
        $data = $request->validated();
        $payload = [
            ...$current,
            ...Arr::except($data, ['logo_file', 'sidebar_logo_file', 'app_icon_file', 'favicon_file']),
            'sidebar_default_collapsed' => (bool) ($data['sidebar_default_collapsed'] ?? false),
        ];

        foreach ([
            'logo_file' => 'logo_url',
            'sidebar_logo_file' => 'sidebar_logo_url',
            'app_icon_file' => 'app_icon_url',
            'favicon_file' => 'favicon_url',
        ] as $fileKey => $settingKey) {
            if ($request->hasFile($fileKey)) {
                $payload[$settingKey] = $this->storeBrandAsset($request->file($fileKey));
            }
        }

        Setting::putValue('app.branding', $payload);

        return response()->json([
            'message' => 'Branding settings updated.',
            'data' => $this->branding(),
        ]);
    }

    private function branding(): array
    {
        return Setting::getValue('app.branding', [
            'app_name' => config('app.name', 'PharmaNP'),
            'logo_url' => null,
            'sidebar_logo_url' => null,
            'app_icon_url' => null,
            'favicon_url' => null,
            'accent_color' => '#0f766e',
            'layout' => 'vertical',
            'sidebar_default_collapsed' => false,
        ]);
    }

    private function storeBrandAsset(UploadedFile $file): string
    {
        return Storage::disk('public')->url($file->store('settings/branding', 'public'));
    }
}
