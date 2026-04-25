<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CurrentUserController;
use App\Http\Controllers\SpaController;
use App\Modules\Accounting\Http\Controllers\VoucherController;
use App\Modules\Core\Http\Controllers\DashboardController;
use App\Modules\Core\Http\Controllers\SystemUpdateController;
use App\Modules\ImportExport\Http\Controllers\ImportWizardController;
use App\Modules\Inventory\Http\Controllers\InventoryMasterController;
use App\Modules\Inventory\Http\Controllers\ProductController;
use App\Modules\MR\Http\Controllers\MedicalRepresentativeController;
use App\Modules\MR\Http\Controllers\MrPerformanceController;
use App\Modules\MR\Http\Controllers\RepresentativeVisitController;
use App\Modules\Party\Http\Controllers\CustomerController;
use App\Modules\Party\Http\Controllers\SupplierController;
use App\Modules\Purchase\Http\Controllers\PurchaseController;
use App\Modules\Purchase\Http\Controllers\PurchaseOrderController;
use App\Modules\Reports\Http\Controllers\ReportController;
use App\Modules\Sales\Http\Controllers\SalesInvoiceController;
use App\Modules\Sales\Http\Controllers\ProductLookupController;
use App\Modules\Setup\Http\Controllers\BrandingController;
use App\Modules\Setup\Http\Controllers\FeatureCatalogController;
use App\Modules\Setup\Http\Controllers\ProfileController;
use App\Modules\Setup\Http\Controllers\RolePermissionController;
use App\Modules\Setup\Http\Controllers\SetupController;
use App\Modules\Setup\Http\Controllers\UserManagementController;
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
        Route::get('/setup/branding', [BrandingController::class, 'show'])->name('setup.branding.show');
        Route::put('/setup/branding', [BrandingController::class, 'update'])->name('setup.branding.update');
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/setup/roles', [RolePermissionController::class, 'index'])->name('setup.roles.index');
        Route::post('/setup/roles', [RolePermissionController::class, 'store'])->name('setup.roles.store');
        Route::put('/setup/roles/{role}', [RolePermissionController::class, 'update'])->name('setup.roles.update');
        Route::delete('/setup/roles/{role}', [RolePermissionController::class, 'destroy'])->name('setup.roles.destroy');
        Route::get('/setup/users', [UserManagementController::class, 'index'])->name('setup.users.index');
        Route::post('/setup/users', [UserManagementController::class, 'store'])->name('setup.users.store');
        Route::put('/setup/users/{user}', [UserManagementController::class, 'update'])->name('setup.users.update');
        Route::delete('/setup/users/{user}', [UserManagementController::class, 'destroy'])->name('setup.users.destroy');

        Route::get('/inventory/products/meta', [ProductController::class, 'meta'])->name('inventory.products.meta');
        Route::post('/inventory/companies/quick', [InventoryMasterController::class, 'company'])->name('inventory.companies.quick');
        Route::post('/inventory/units/quick', [InventoryMasterController::class, 'unit'])->name('inventory.units.quick');
        Route::post('/inventory/categories/quick', [InventoryMasterController::class, 'category'])->name('inventory.categories.quick');
        Route::get('/inventory/masters/{master}', [InventoryMasterController::class, 'index'])->whereIn('master', ['companies', 'units', 'categories'])->name('inventory.masters.index');
        Route::post('/inventory/masters/{master}', [InventoryMasterController::class, 'store'])->whereIn('master', ['companies', 'units', 'categories'])->name('inventory.masters.store');
        Route::put('/inventory/masters/{master}/{id}', [InventoryMasterController::class, 'update'])->whereIn('master', ['companies', 'units', 'categories'])->name('inventory.masters.update');
        Route::delete('/inventory/masters/{master}/{id}', [InventoryMasterController::class, 'destroy'])->whereIn('master', ['companies', 'units', 'categories'])->name('inventory.masters.destroy');
        Route::apiResource('inventory/products', ProductController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('/suppliers/options', [SupplierController::class, 'options'])->name('suppliers.options');
        Route::apiResource('suppliers', SupplierController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/customers/options', [CustomerController::class, 'options'])->name('customers.options');
        Route::apiResource('customers', CustomerController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::apiResource('purchase/orders', PurchaseOrderController::class)->only(['index', 'store']);
        Route::apiResource('purchases', PurchaseController::class)->only(['index', 'store']);

        Route::get('/sales/product-lookup', ProductLookupController::class)->name('sales.product-lookup');
        Route::apiResource('sales/invoices', SalesInvoiceController::class)->only(['index', 'store', 'show']);
        Route::get('/mr/performance', MrPerformanceController::class)->name('mr.performance');
        Route::get('/mr/options', [MedicalRepresentativeController::class, 'options'])->name('mr.options');
        Route::apiResource('mr/representatives', MedicalRepresentativeController::class)->only(['index', 'store', 'update', 'destroy'])->parameter('representatives', 'representative');
        Route::apiResource('mr/visits', RepresentativeVisitController::class)->only(['index', 'store', 'update', 'destroy'])->parameter('visits', 'visit');
        Route::apiResource('accounting/vouchers', VoucherController::class)->only(['index', 'store']);

        Route::get('/reports/{report}', ReportController::class)->name('reports.show');

        Route::get('/imports/targets', [ImportWizardController::class, 'targets'])->name('imports.targets');
        Route::post('/imports/preview', [ImportWizardController::class, 'preview'])->name('imports.preview');
        Route::post('/imports/confirm', [ImportWizardController::class, 'confirm'])->name('imports.confirm');
        Route::get('/imports/{job}/rejected.csv', [ImportWizardController::class, 'rejected'])->name('imports.rejected');
    });

    Route::get('/purchases/{purchase}/print', [PurchaseController::class, 'print'])->name('purchases.print');
    Route::get('/purchases/{purchase}/pdf', [PurchaseController::class, 'pdf'])->name('purchases.pdf');
    Route::get('/sales/invoices/{invoice}/print', [SalesInvoiceController::class, 'print'])->name('sales.invoices.print');
    Route::get('/sales/invoices/{invoice}/pdf', [SalesInvoiceController::class, 'pdf'])->name('sales.invoices.pdf');

    Route::get('/admin/system/update-check', SpaController::class)->name('system.update-check');
    Route::get('/app/{any?}', SpaController::class)->where('any', '.*')->name('app');
});
