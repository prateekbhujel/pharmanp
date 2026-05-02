<?php

namespace App\Modules\Reports\Contracts;

use Illuminate\Http\Request;

interface DumpingReportServiceInterface
{
    public function slowMoving(Request $request, int $perPage): array;
}
