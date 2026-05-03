<?php

namespace App\Modules\Party\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Party\Repositories\CustomerLedgerRepository;
use App\Modules\Party\Repositories\Interfaces\CustomerLedgerRepositoryInterface;
use App\Modules\Party\Repositories\Interfaces\PartyRepositoryInterface;
use App\Modules\Party\Repositories\PartyRepository;

class PartyServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(PartyRepositoryInterface::class, PartyRepository::class);
        $this->app->bind(CustomerLedgerRepositoryInterface::class, CustomerLedgerRepository::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
