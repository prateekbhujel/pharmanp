<?php

namespace App\Http\Controllers;

use App\Core\Support\AssetUrl;
use App\Core\Support\ProductMeta;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="CORE - Current User",
 *     description="API endpoint for authenticated user, permissions and branding bootstrap"
 * )
 */
class CurrentUserController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/me",
     *     summary="Api Me",
     *     tags={"CORE - Current User"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing(['roles:id,name', 'branch:id,name,code,type', 'medicalRepresentative:id,name']);
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
        ]);

        foreach (['logo_url', 'sidebar_logo_url', 'app_icon_url', 'favicon_url'] as $key) {
            $branding[$key] = AssetUrl::resolve($branding[$key] ?? null);
        }
        $branding['product'] = ProductMeta::payload();

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
                'branch_id' => $user->branch_id,
                'branch' => $user->branch ? [
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                    'code' => $user->branch->code,
                    'type' => $user->branch->type,
                ] : null,
                'medical_representative_id' => $user->medical_representative_id,
                'medical_representative' => $user->medicalRepresentative ? [
                    'id' => $user->medicalRepresentative->id,
                    'name' => $user->medicalRepresentative->name,
                ] : null,
                'impersonating' => $request->session()->has('impersonator_user_id'),
                'impersonator_user_id' => $request->session()->get('impersonator_user_id'),
            ],
            'branding' => $branding,
        ]);
    }
}
