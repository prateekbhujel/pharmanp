<?php

namespace App\Modules\Accounting\Providers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Accounting\Repositories\AccountTransactionRepository;
use App\Modules\Accounting\Repositories\ExpenseRepository;
use App\Modules\Accounting\Repositories\Interfaces\AccountTransactionRepositoryInterface;
use App\Modules\Accounting\Repositories\Interfaces\ExpenseRepositoryInterface;
use App\Modules\Accounting\Repositories\Interfaces\PartyBalanceRepositoryInterface;
use App\Modules\Accounting\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Modules\Accounting\Repositories\Interfaces\VoucherRepositoryInterface;
use App\Modules\Accounting\Repositories\PartyBalanceRepository;
use App\Modules\Accounting\Repositories\PaymentRepository;
use App\Modules\Accounting\Repositories\VoucherRepository;
use App\Modules\Base\Providers\BaseModuleServiceProvider;

class AccountingServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(AccountTransactionRepositoryInterface::class, AccountTransactionRepository::class);
        $this->app->bind(ExpenseRepositoryInterface::class, ExpenseRepository::class);
        $this->app->bind(PartyBalanceRepositoryInterface::class, PartyBalanceRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
        $this->app->bind(VoucherRepositoryInterface::class, VoucherRepository::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
