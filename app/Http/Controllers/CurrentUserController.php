<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing(['roles:id,name', 'medicalRepresentative:id,name']);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_owner' => $user->is_owner,
                'is_active' => $user->is_active,
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                'roles' => $user->roles->pluck('name')->values(),
                'company_id' => $user->company_id,
                'store_id' => $user->store_id,
                'medical_representative_id' => $user->medical_representative_id,
                'medical_representative' => $user->medicalRepresentative ? [
                    'id' => $user->medicalRepresentative->id,
                    'name' => $user->medicalRepresentative->name,
                ] : null,
            ],
            'branding' => Setting::getValue('app.branding', [
                'app_name' => config('app.name', 'PharmaNP'),
                'logo_url' => null,
                'sidebar_logo_url' => null,
                'app_icon_url' => null,
                'favicon_url' => null,
                'accent_color' => '#0f766e',
                'layout' => 'vertical',
                'sidebar_default_collapsed' => false,
            ]),
        ]);
    }
}
