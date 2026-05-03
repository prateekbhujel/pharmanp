<?php

namespace App\Modules\Sales\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Sales\Repositories\Interfaces\SalesInvoiceRepositoryInterface;
use App\Modules\Sales\Repositories\SalesInvoiceRepository;

class SalesServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(SalesInvoiceRepositoryInterface::class, SalesInvoiceRepository::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
