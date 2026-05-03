<?php

namespace App\Modules\Party\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Party\Contracts\PartyServiceInterface;
use App\Modules\Party\Repositories\Interfaces\PartyRepositoryInterface;
use App\Modules\Party\Repositories\PartyRepository;
use App\Modules\Party\Services\PartyService;

class PartyServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(PartyRepositoryInterface::class, PartyRepository::class);
        $this->app->bind(PartyServiceInterface::class, PartyService::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
