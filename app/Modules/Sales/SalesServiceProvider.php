<?php

namespace App\Modules\Sales;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\Sales\Contracts\SalesInvoiceServiceInterface;
use App\Modules\Sales\Services\SalesInvoiceService;

class SalesServiceProvider extends ModuleServiceProvider
{
    protected function bindings(): array
    {
        return [
            SalesInvoiceServiceInterface::class => SalesInvoiceService::class,
        ];
    }
}
