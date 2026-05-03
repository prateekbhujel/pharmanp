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
    public function register()
    {
        $this->app->bind(AccessControlServiceInterface::class, AccessControlService::class);
        $this->app->bind(AccessScopeServiceInterface::class, AccessScopeService::class);
        $this->app->bind(FeatureCatalogServiceInterface::class, FeatureCatalogService::class);
        $this->app->bind(OrganizationStructureServiceInterface::class, OrganizationStructureService::class);
        $this->app->bind(SetupServiceInterface::class, SetupService::class);
        $this->app->bind(TargetServiceInterface::class, TargetService::class);
        $this->app->bind(UserManagementServiceInterface::class, UserManagementService::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
