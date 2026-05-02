<?php

namespace App\Modules\Core\Http\Controllers;

use App\Core\Modules\ModuleRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ModuleCatalogController extends Controller
{
    public function __invoke(ModuleRegistry $modules): JsonResponse
    {
        return response()->json([
            'data' => $modules->toArray(),
        ]);
    }
}
