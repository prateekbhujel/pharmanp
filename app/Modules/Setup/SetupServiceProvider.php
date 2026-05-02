<?php

namespace App\Modules\Setup;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\Setup\Contracts\AccessControlServiceInterface;
use App\Modules\Setup\Contracts\FeatureCatalogServiceInterface;
use App\Modules\Setup\Contracts\SetupServiceInterface;
use App\Modules\Setup\Contracts\UserManagementServiceInterface;
use App\Modules\Setup\Services\AccessControlService;
use App\Modules\Setup\Services\FeatureCatalogService;
use App\Modules\Setup\Services\SetupService;
use App\Modules\Setup\Services\UserManagementService;

class SetupServiceProvider extends ModuleServiceProvider
{
    protected function bindings(): array
    {
        return [
            AccessControlServiceInterface::class => AccessControlService::class,
            FeatureCatalogServiceInterface::class => FeatureCatalogService::class,
            SetupServiceInterface::class => SetupService::class,
            UserManagementServiceInterface::class => UserManagementService::class,
        ];
    }
}
