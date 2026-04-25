<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Modules\Setup\Http\Requests\BrandingSettingsRequest;
use Illuminate\Http\JsonResponse;

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

        Setting::putValue('app.branding', [
            ...$current,
            ...$data,
            'sidebar_default_collapsed' => (bool) ($data['sidebar_default_collapsed'] ?? false),
        ]);

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
}
