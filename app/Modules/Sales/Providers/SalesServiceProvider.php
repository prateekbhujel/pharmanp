<?php

namespace App\Modules\Sales\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Sales\Contracts\SalesInvoiceServiceInterface;
use App\Modules\Sales\Services\SalesInvoiceService;

class SalesServiceProvider extends BaseModuleServiceProvider
{
    protected function bindings(): array
    {
        return [
            SalesInvoiceServiceInterface::class => SalesInvoiceService::class,
        ];
    }
}
