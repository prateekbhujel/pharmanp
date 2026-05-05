<?php

namespace App\Modules\MR\Providers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Base\Providers\BaseModuleServiceProvider;
use App\Modules\MR\Repositories\BranchRepository;
use App\Modules\MR\Repositories\Interfaces\BranchRepositoryInterface;
use App\Modules\MR\Repositories\Interfaces\MrRepositoryInterface;
use App\Modules\MR\Repositories\MrRepository;

class MrServiceProvider extends BaseModuleServiceProvider
{
    public function register()
    {
        $this->app->bind(BranchRepositoryInterface::class, BranchRepository::class);
        $this->app->bind(MrRepositoryInterface::class, MrRepository::class);
    }

    public function boot()
    {
        $this->loadModuleRoutes(__DIR__.'/..');
    }
}
