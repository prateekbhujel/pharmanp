<?php

namespace App\Modules\Accounting\Providers;

use App\Modules\Accounting\Contracts\AccountTransactionPostingServiceInterface;
use App\Modules\Accounting\Contracts\PayableServiceInterface;
use App\Modules\Accounting\Contracts\PaymentSettlementServiceInterface;
use App\Modules\Accounting\Contracts\ReceivableServiceInterface;
use App\Modules\Accounting\Contracts\VoucherServiceInterface;
use App\Modules\Accounting\Repositories\AccountTransactionRepository;
use App\Modules\Accounting\Repositories\Interfaces\AccountTransactionRepositoryInterface;
use App\Modules\Accounting\Repositories\Interfaces\PartyBalanceRepositoryInterface;
use App\Modules\Accounting\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Modules\Accounting\Repositories\Interfaces\VoucherRepositoryInterface;
use App\Modules\Accounting\Repositories\PartyBalanceRepository;
use App\Modules\Accounting\Repositories\PaymentRepository;
use App\Modules\Accounting\Repositories\VoucherRepository;
use App\Modules\Accounting\Services\AccountingPostingService;
use App\Modules\Accounting\Services\PayableService;
use App\Modules\Accounting\Services\PaymentSettlementService;
use App\Modules\Accounting\Services\ReceivableService;
use App\Modules\Accounting\Services\VoucherService;
use App\Modules\Base\Providers\BaseModuleServiceProvider;

class AccountingServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(AccountTransactionRepositoryInterface::class, AccountTransactionRepository::class);
        $this->app->bind(PartyBalanceRepositoryInterface::class, PartyBalanceRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
        $this->app->bind(VoucherRepositoryInterface::class, VoucherRepository::class);
        $this->app->bind(AccountTransactionPostingServiceInterface::class, AccountingPostingService::class);
        $this->app->bind(PaymentSettlementServiceInterface::class, PaymentSettlementService::class);
        $this->app->bind(PayableServiceInterface::class, PayableService::class);
        $this->app->bind(ReceivableServiceInterface::class, ReceivableService::class);
        $this->app->bind(VoucherServiceInterface::class, VoucherService::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
