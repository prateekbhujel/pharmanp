<?php

namespace App\Modules\ImportExport\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\ImportExport\Contracts\ImportPreviewServiceInterface;
use App\Modules\ImportExport\Contracts\PurchaseOcrServiceInterface;
use App\Modules\ImportExport\Services\ImportPreviewService;
use App\Modules\ImportExport\Services\PurchaseOcrService;

class ImportExportServiceProvider extends BaseModuleServiceProvider
{
    protected function bindings(): array
    {
        return [
            ImportPreviewServiceInterface::class => ImportPreviewService::class,
            PurchaseOcrServiceInterface::class => PurchaseOcrService::class,
        ];
    }
}
