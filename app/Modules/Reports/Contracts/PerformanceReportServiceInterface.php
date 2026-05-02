<?php

namespace App\Modules\Reports\Contracts;

use Illuminate\Http\Request;

interface PerformanceReportServiceInterface
{
    public function mrVsProduct(Request $request, int $perPage): array;

    public function mrVsDivision(Request $request, int $perPage): array;

    public function mrVsSales(Request $request, int $perPage): array;

    public function companyVsCustomer(Request $request, int $perPage): array;
}
