<?php

namespace App\Modules\Reports\Contracts;

use Illuminate\Http\Request;

interface ReportServiceInterface
{
    public function run(string $report, Request $request): array;
}
