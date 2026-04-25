<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_owner' => $user->is_owner,
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                'company_id' => $user->company_id,
                'store_id' => $user->store_id,
            ],
            'branding' => Setting::getValue('app.branding', [
                'app_name' => config('app.name', 'PharmaNP'),
                'logo_url' => null,
                'sidebar_logo_url' => null,
                'accent_color' => '#0f766e',
                'layout' => 'vertical',
                'sidebar_default_collapsed' => false,
            ]),
        ]);
    }
}
