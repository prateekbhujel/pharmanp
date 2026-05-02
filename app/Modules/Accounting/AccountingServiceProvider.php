<?php

namespace App\Modules\Accounting;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\Accounting\Contracts\AccountTransactionPostingServiceInterface;
use App\Modules\Accounting\Contracts\VoucherServiceInterface;
use App\Modules\Accounting\Services\AccountTransactionPostingService;
use App\Modules\Accounting\Services\VoucherService;

class AccountingServiceProvider extends ModuleServiceProvider
{
    protected function bindings(): array
    {
        return [
            AccountTransactionPostingServiceInterface::class => AccountTransactionPostingService::class,
            VoucherServiceInterface::class => VoucherService::class,
        ];
    }
}
