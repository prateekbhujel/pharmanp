<?php

namespace App\Modules\ImportExport;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\ImportExport\Contracts\ImportPreviewServiceInterface;
use App\Modules\ImportExport\Contracts\PurchaseOcrServiceInterface;
use App\Modules\ImportExport\Services\ImportPreviewService;
use App\Modules\ImportExport\Services\PurchaseOcrService;

class ImportExportServiceProvider extends ModuleServiceProvider
{
    protected function bindings(): array
    {
        return [
            ImportPreviewServiceInterface::class => ImportPreviewService::class,
            PurchaseOcrServiceInterface::class => PurchaseOcrService::class,
        ];
    }
}
