<?php

namespace App\Modules\Accounting;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\Accounting\Contracts\AccountTransactionPostingServiceInterface;
use App\Modules\Accounting\Contracts\PayableServiceInterface;
use App\Modules\Accounting\Contracts\PaymentSettlementServiceInterface;
use App\Modules\Accounting\Contracts\ReceivableServiceInterface;
use App\Modules\Accounting\Contracts\VoucherServiceInterface;
use App\Modules\Accounting\Services\AccountingPostingService;
use App\Modules\Accounting\Services\PayableService;
use App\Modules\Accounting\Services\PaymentSettlementService;
use App\Modules\Accounting\Services\ReceivableService;
use App\Modules\Accounting\Services\VoucherService;

class AccountingServiceProvider extends ModuleServiceProvider
{
    protected function bindings(): array
    {
        return [
            AccountTransactionPostingServiceInterface::class => AccountingPostingService::class,
            PaymentSettlementServiceInterface::class => PaymentSettlementService::class,
            PayableServiceInterface::class => PayableService::class,
            ReceivableServiceInterface::class => ReceivableService::class,
            VoucherServiceInterface::class => VoucherService::class,
        ];
    }
}
