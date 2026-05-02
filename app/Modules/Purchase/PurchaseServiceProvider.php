<?php

namespace App\Modules\Purchase;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\Purchase\Contracts\PurchaseEntryServiceInterface;
use App\Modules\Purchase\Contracts\PurchaseOrderServiceInterface;
use App\Modules\Purchase\Contracts\PurchaseReturnServiceInterface;
use App\Modules\Purchase\Services\PurchaseEntryService;
use App\Modules\Purchase\Services\PurchaseOrderService;
use App\Modules\Purchase\Services\PurchaseReturnService;

class PurchaseServiceProvider extends ModuleServiceProvider
{
    protected function bindings(): array
    {
        return [
            PurchaseEntryServiceInterface::class => PurchaseEntryService::class,
            PurchaseOrderServiceInterface::class => PurchaseOrderService::class,
            PurchaseReturnServiceInterface::class => PurchaseReturnService::class,
        ];
    }
}
