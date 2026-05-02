<?php

namespace App\Modules\Analytics;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\Analytics\Contracts\PharmaSignalServiceInterface;
use App\Modules\Analytics\Services\PharmaSignalService;

class AnalyticsServiceProvider extends ModuleServiceProvider
{
    protected function bindings(): array
    {
        return [
            PharmaSignalServiceInterface::class => PharmaSignalService::class,
        ];
    }
}
