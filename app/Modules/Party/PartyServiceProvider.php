<?php

namespace App\Modules\Party;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\Party\Contracts\PartyServiceInterface;
use App\Modules\Party\Services\PartyService;

class PartyServiceProvider extends ModuleServiceProvider
{
    protected function bindings(): array
    {
        return [
            PartyServiceInterface::class => PartyService::class,
        ];
    }
}
