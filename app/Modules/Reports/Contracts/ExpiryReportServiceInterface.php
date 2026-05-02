<?php

namespace App\Modules\Reports\Contracts;

use Illuminate\Http\Request;

interface ExpiryReportServiceInterface
{
    public function buckets(Request $request, int $perPage): array;
}
