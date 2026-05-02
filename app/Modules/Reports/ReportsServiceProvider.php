<?php

namespace App\Modules\Reports;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\Reports\Contracts\ReportServiceInterface;
use App\Modules\Reports\Services\ReportService;

class ReportsServiceProvider extends ModuleServiceProvider
{
    protected function bindings(): array
    {
        return [
            ReportServiceInterface::class => ReportService::class,
        ];
    }
}
