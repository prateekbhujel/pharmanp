<?php

namespace App\Modules\Party\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Party\Contracts\PartyServiceInterface;
use App\Modules\Party\Services\PartyService;

class PartyServiceProvider extends BaseModuleServiceProvider
{
    protected function bindings(): array
    {
        return [
            PartyServiceInterface::class => PartyService::class,
        ];
    }
}
