<?php

namespace App\Modules\Reports\Contracts;

use Illuminate\Http\Request;

interface AgingReportServiceInterface
{
    public function customers(Request $request, int $perPage): array;

    public function suppliers(Request $request, int $perPage): array;
}
