<?php

namespace App\Modules\ImportExport\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;

class ImportExportServiceProvider extends BaseModuleServiceProvider
{
    public function register() {}

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
