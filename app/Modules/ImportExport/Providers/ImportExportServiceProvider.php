<?php

namespace App\Modules\ImportExport\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\ImportExport\Contracts\ImportPreviewServiceInterface;
use App\Modules\ImportExport\Contracts\PurchaseOcrServiceInterface;
use App\Modules\ImportExport\Services\ImportPreviewService;
use App\Modules\ImportExport\Services\PurchaseOcrService;

class ImportExportServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(ImportPreviewServiceInterface::class, ImportPreviewService::class);
        $this->app->bind(PurchaseOcrServiceInterface::class, PurchaseOcrService::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
