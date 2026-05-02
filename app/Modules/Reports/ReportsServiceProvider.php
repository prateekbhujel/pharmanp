<?php

namespace App\Modules\Reports;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\Reports\Contracts\AgingReportServiceInterface;
use App\Modules\Reports\Contracts\DumpingReportServiceInterface;
use App\Modules\Reports\Contracts\ExpiryReportServiceInterface;
use App\Modules\Reports\Contracts\PerformanceReportServiceInterface;
use App\Modules\Reports\Contracts\ReportServiceInterface;
use App\Modules\Reports\Contracts\TargetAchievementServiceInterface;
use App\Modules\Reports\Services\AgingReportService;
use App\Modules\Reports\Services\DumpingReportService;
use App\Modules\Reports\Services\ExpiryReportService;
use App\Modules\Reports\Services\PerformanceReportService;
use App\Modules\Reports\Services\ReportService;
use App\Modules\Reports\Services\TargetAchievementService;

class ReportsServiceProvider extends ModuleServiceProvider
{
    protected function bindings(): array
    {
        return [
            AgingReportServiceInterface::class => AgingReportService::class,
            DumpingReportServiceInterface::class => DumpingReportService::class,
            ExpiryReportServiceInterface::class => ExpiryReportService::class,
            PerformanceReportServiceInterface::class => PerformanceReportService::class,
            ReportServiceInterface::class => ReportService::class,
            TargetAchievementServiceInterface::class => TargetAchievementService::class,
        ];
    }
}
