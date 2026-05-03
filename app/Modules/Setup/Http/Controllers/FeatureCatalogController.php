<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Setup\Services\FeatureCatalogService;
use Illuminate\Http\JsonResponse;

class FeatureCatalogController extends Controller
{
    public function __invoke(FeatureCatalogService $service): JsonResponse
    {
        return response()->json(['data' => $service->grouped()]);
    }
}
