<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Setup\Contracts\FeatureCatalogServiceInterface;
use Illuminate\Http\JsonResponse;

class FeatureCatalogController extends Controller
{
    public function __invoke(FeatureCatalogServiceInterface $service): JsonResponse
    {
        return response()->json(['data' => $service->grouped()]);
    }
}
