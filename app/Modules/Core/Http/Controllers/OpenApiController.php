<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OpenApiController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $path = base_path('docs/openapi/pharmanp.v1.json');

        abort_unless(is_file($path), 404);

        return response()->json(json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR));
    }
}
