<?php

namespace App\Modules\Reports\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
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

class ReportsServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(AgingReportServiceInterface::class, AgingReportService::class);
        $this->app->bind(DumpingReportServiceInterface::class, DumpingReportService::class);
        $this->app->bind(ExpiryReportServiceInterface::class, ExpiryReportService::class);
        $this->app->bind(PerformanceReportServiceInterface::class, PerformanceReportService::class);
        $this->app->bind(ReportServiceInterface::class, ReportService::class);
        $this->app->bind(TargetAchievementServiceInterface::class, TargetAchievementService::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
