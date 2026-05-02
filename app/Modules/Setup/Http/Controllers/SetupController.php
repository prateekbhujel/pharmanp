<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\Services\InstallationService;
use App\Http\Controllers\Controller;
use App\Modules\Setup\Http\Requests\CompleteSetupRequest;
use App\Modules\Setup\Contracts\SetupServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function show(): View
    {
        return view('setup.show');
    }

    public function status(InstallationService $installation): JsonResponse
    {
        return response()->json(['data' => $installation->status()]);
    }

    public function complete(CompleteSetupRequest $request, SetupServiceInterface $service): JsonResponse
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
