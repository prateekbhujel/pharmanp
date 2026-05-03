<?php

namespace App\Modules\Analytics\Providers;

use App\Modules\Analytics\Contracts\PharmaSignalServiceInterface;
use App\Modules\Analytics\Services\PharmaSignalService;
use App\Modules\Base\Providers\BaseModuleServiceProvider;

class AnalyticsServiceProvider extends BaseModuleServiceProvider
{
    protected function bindings(): array
    {
        return [
            PharmaSignalServiceInterface::class => PharmaSignalService::class,
        ];
    }
}
