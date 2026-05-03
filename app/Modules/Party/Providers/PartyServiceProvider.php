<?php

namespace App\Modules\Party\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Party\Repositories\Interfaces\PartyRepositoryInterface;
use App\Modules\Party\Repositories\PartyRepository;

class PartyServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(PartyRepositoryInterface::class, PartyRepository::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
