<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CurrentUserController;
use App\Http\Controllers\SpaController;
use App\Modules\Core\Http\Controllers\DashboardController;
use App\Modules\Core\Http\Controllers\SystemUpdateController;
use App\Modules\ImportExport\Http\Controllers\ImportWizardController;
use App\Modules\Inventory\Http\Controllers\InventoryMasterController;
use App\Modules\Inventory\Http\Controllers\ProductController;
use App\Modules\MR\Http\Controllers\MrPerformanceController;
use App\Modules\Sales\Http\Controllers\ProductLookupController;
use App\Modules\Setup\Http\Controllers\FeatureCatalogController;
use App\Modules\Setup\Http\Controllers\SetupInviteController;
use App\Modules\Setup\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! app(\App\Core\Services\InstallationService::class)->installed()) {
        return redirect()->route('setup.show');
    }

    return auth()->check()
        ? redirect()->route('app')
        : redirect()->route('login');
})->name('home');

Route::middleware('not_installed')->group(function () {
    Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
    Route::get('/setup/status', [SetupController::class, 'status'])->name('setup.status');
    Route::post('/setup/complete', [SetupController::class, 'complete'])->name('setup.complete');
});

Route::middleware(['installed', 'guest'])->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware(['installed', 'auth'])
    ->name('logout');

Route::middleware(['installed', 'auth'])->group(function () {
    Route::prefix('api/v1')->name('api.')->group(function () {
        Route::get('/me', CurrentUserController::class)->name('me');
        Route::get('/dashboard/summary', DashboardController::class)->name('dashboard.summary');
        Route::get('/system/update-check', SystemUpdateController::class)->name('system.update-check');
        Route::get('/setup/features', FeatureCatalogController::class)->name('setup.features');
        Route::get('/setup/invites', [SetupInviteController::class, 'index'])->name('setup.invites.index');
        Route::post('/setup/invites', [SetupInviteController::class, 'store'])->name('setup.invites.store');
        Route::post('/setup/invites/{invite}/revoke', [SetupInviteController::class, 'revoke'])->name('setup.invites.revoke');

        Route::get('/inventory/products/meta', [ProductController::class, 'meta'])->name('inventory.products.meta');
        Route::post('/inventory/companies/quick', [InventoryMasterController::class, 'company'])->name('inventory.companies.quick');
        Route::post('/inventory/units/quick', [InventoryMasterController::class, 'unit'])->name('inventory.units.quick');
        Route::post('/inventory/categories/quick', [InventoryMasterController::class, 'category'])->name('inventory.categories.quick');
        Route::apiResource('inventory/products', ProductController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('/sales/product-lookup', ProductLookupController::class)->name('sales.product-lookup');
        Route::get('/mr/performance', MrPerformanceController::class)->name('mr.performance');

        Route::get('/imports/targets', [ImportWizardController::class, 'targets'])->name('imports.targets');
        Route::post('/imports/preview', [ImportWizardController::class, 'preview'])->name('imports.preview');
        Route::post('/imports/confirm', [ImportWizardController::class, 'confirm'])->name('imports.confirm');
    });

    Route::get('/admin/system/update-check', SpaController::class)->name('system.update-check');
    Route::get('/app/{any?}', SpaController::class)->where('any', '.*')->name('app');
});
