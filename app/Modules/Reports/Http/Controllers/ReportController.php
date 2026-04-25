<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __invoke(string $report, Request $request, ReportService $service): JsonResponse
    {
        return response()->json($service->run($report, $request));
    }
}
