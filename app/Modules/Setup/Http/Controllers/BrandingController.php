<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Http\Controllers\ModularController;
use App\Modules\Setup\Http\Requests\BrandingSettingsRequest;
use App\Modules\Setup\Services\BrandingSettingsService;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class BrandingController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/setup/branding",
     *     summary="Api Setup Branding Show",
     *     tags={"SETUP - Branding"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function show(BrandingSettingsService $service): JsonResponse
    {
        abort_unless(request()->user()?->is_owner || request()->user()?->can('setup.manage'), 403);

        return response()->json(['data' => $service->brandingPayload()]);
    }

    /**
     * @OA\Post(
     *     path="/setup/branding",
     *     summary="Api Setup Branding Store",
     *     tags={"SETUP - Branding"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(BrandingSettingsRequest $request, BrandingSettingsService $service): JsonResponse
    {
        $branding = $service->updateBranding(
            $request->validated(),
            [
                'logo_file' => $request->file('logo_file'),
                'sidebar_logo_file' => $request->file('sidebar_logo_file'),
                'app_icon_file' => $request->file('app_icon_file'),
                'favicon_file' => $request->file('favicon_file'),
            ],
        );

        return response()->json([
            'message' => 'Branding settings updated.',
            'data' => $branding,
        ]);
    }
}
