<?php

namespace App\Modules\MR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MR\Services\MrPerformanceService;
use Illuminate\Http\JsonResponse;

class MrPerformanceController extends Controller
{
    public function __invoke(MrPerformanceService $service): JsonResponse
    {
        return response()->json(['data' => $service->monthly()]);
    }
}
