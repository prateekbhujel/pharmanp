<?php

namespace App\Modules\Sales\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Sales\Repositories\Interfaces\SalesInvoiceRepositoryInterface;
use App\Modules\Sales\Repositories\Interfaces\SalesReturnRepositoryInterface;
use App\Modules\Sales\Repositories\SalesInvoiceRepository;
use App\Modules\Sales\Repositories\SalesReturnRepository;

class SalesServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(SalesInvoiceRepositoryInterface::class, SalesInvoiceRepository::class);
        $this->app->bind(SalesReturnRepositoryInterface::class, SalesReturnRepository::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
