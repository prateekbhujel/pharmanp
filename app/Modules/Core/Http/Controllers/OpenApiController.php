<?php

namespace App\Modules\Core\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Http\Controllers\Controller;
use App\Modules\Core\OpenApi\OpenApiSpecBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpenApiController extends Controller
{
    public function __construct(private readonly OpenApiSpecBuilder $builder) {}

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json($this->builder->build($request));
    }
}
