<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\Services\InstallationService;
use App\Http\Controllers\ModularController;
use App\Modules\Setup\Http\Requests\CompleteSetupRequest;
use App\Modules\Setup\Services\SetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class SetupController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/setup/status",
     *     summary="Read installation status",
     *     tags={"SETUP - Administration"},
     *
     *     @OA\Response(response=200, description="Installation status response")
     * )
     */
    public function show(): View
    {
        return view('setup.show');
    }

    public function status(InstallationService $installation): JsonResponse
    {
        return response()->json(['data' => $installation->status()]);
    }

    public function complete(CompleteSetupRequest $request, SetupService $service): JsonResponse
    {
        $result = $service->complete($request->validated());

        return response()->json([
            'message' => 'PharmaNP setup completed.',
            'data' => [
                'company_id' => $result['company']->id,
                'store_id' => $result['store']->id,
                'admin_id' => $result['admin']->id,
            ],
        ], 201);
    }
}
