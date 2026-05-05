<?php

namespace App\Modules\ImportExport\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\ImportExport\Repositories\ExportRepository;
use App\Modules\ImportExport\Repositories\ImportJobRepository;
use App\Modules\ImportExport\Repositories\Interfaces\ExportRepositoryInterface;
use App\Modules\ImportExport\Repositories\Interfaces\ImportJobRepositoryInterface;

class ImportExportServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(ImportJobRepositoryInterface::class, ImportJobRepository::class);
        $this->app->bind(ExportRepositoryInterface::class, ExportRepository::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
