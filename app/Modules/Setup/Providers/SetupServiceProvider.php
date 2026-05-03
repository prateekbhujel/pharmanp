<?php

namespace App\Modules\Setup\Providers;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\Setup\Contracts\AccessControlServiceInterface;
use App\Modules\Setup\Contracts\AccessScopeServiceInterface;
use App\Modules\Setup\Contracts\FeatureCatalogServiceInterface;
use App\Modules\Setup\Contracts\OrganizationStructureServiceInterface;
use App\Modules\Setup\Contracts\SetupServiceInterface;
use App\Modules\Setup\Contracts\TargetServiceInterface;
use App\Modules\Setup\Contracts\UserManagementServiceInterface;
use App\Modules\Setup\Services\AccessControlService;
use App\Modules\Setup\Services\AccessScopeService;
use App\Modules\Setup\Services\FeatureCatalogService;
use App\Modules\Setup\Services\OrganizationStructureService;
use App\Modules\Setup\Services\SetupService;
use App\Modules\Setup\Services\TargetService;
use App\Modules\Setup\Services\UserManagementService;

class SetupServiceProvider extends BaseModuleServiceProvider
{
    protected function bindings(): array
    {
        return [
            AccessControlServiceInterface::class => AccessControlService::class,
            AccessScopeServiceInterface::class => AccessScopeService::class,
            FeatureCatalogServiceInterface::class => FeatureCatalogService::class,
            OrganizationStructureServiceInterface::class => OrganizationStructureService::class,
            SetupServiceInterface::class => SetupService::class,
            TargetServiceInterface::class => TargetService::class,
            UserManagementServiceInterface::class => UserManagementService::class,
        ];
    }
}
