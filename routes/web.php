<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CurrentUserController;
use App\Http\Controllers\SpaController;
use App\Modules\Accounting\Http\Controllers\ExpenseController;
use App\Modules\Accounting\Http\Controllers\PaymentController;
use App\Modules\Accounting\Http\Controllers\VoucherController;
use App\Modules\Core\Http\Controllers\DashboardController;
use App\Modules\Core\Http\Controllers\SystemUpdateController;
use App\Modules\ImportExport\Http\Controllers\ExportController;
use App\Modules\ImportExport\Http\Controllers\ImportWizardController;
use App\Modules\Inventory\Http\Controllers\InventoryMasterController;
use App\Modules\Inventory\Http\Controllers\BatchController;
use App\Modules\Inventory\Http\Controllers\ProductController;
use App\Modules\Inventory\Http\Controllers\StockAdjustmentController;
use App\Modules\Inventory\Http\Controllers\StockMovementController;
use App\Modules\MR\Http\Controllers\MedicalRepresentativeController;
use App\Modules\MR\Http\Controllers\MrBranchSalesController;
use App\Modules\MR\Http\Controllers\MrPerformanceController;
use App\Modules\MR\Http\Controllers\BranchController;
use App\Modules\MR\Http\Controllers\RepresentativeVisitController;
use App\Modules\Party\Http\Controllers\CustomerController;
use App\Modules\Party\Http\Controllers\SupplierController;
use App\Modules\Purchase\Http\Controllers\PurchaseController;
use App\Modules\Purchase\Http\Controllers\PurchaseOrderController;
use App\Modules\Purchase\Http\Controllers\PurchaseReturnController;
use App\Modules\Party\Http\Controllers\CustomerLedgerController;
use App\Modules\Reports\Http\Controllers\ReportController;
use App\Modules\Sales\Http\Controllers\SalesInvoiceController;
use App\Modules\Sales\Http\Controllers\SalesReturnController;
use App\Modules\Sales\Http\Controllers\ProductLookupController;
use App\Modules\Setup\Http\Controllers\BrandingController;
use App\Modules\Setup\Http\Controllers\DropdownOptionController;
use App\Modules\Setup\Http\Controllers\FeatureCatalogController;
use App\Modules\Setup\Http\Controllers\FiscalYearController;
use App\Modules\Setup\Http\Controllers\PartyTypeController;
use App\Modules\Setup\Http\Controllers\ProfileController;
use App\Modules\Setup\Http\Controllers\RolePermissionController;
use App\Modules\Setup\Http\Controllers\SetupController;
use App\Modules\Setup\Http\Controllers\SettingsAdminController;
use App\Modules\Setup\Http\Controllers\SupplierTypeController;
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
        Route::get('/search', \App\Modules\Core\Http\Controllers\GlobalSearchController::class)->name('search');
        Route::get('/system/update-check', SystemUpdateController::class)->name('system.update-check');
        Route::get('/setup/features', FeatureCatalogController::class)->name('setup.features');
        Route::get('/setup/branding', [BrandingController::class, 'show'])->name('setup.branding.show');
        Route::post('/setup/branding', [BrandingController::class, 'update'])->name('setup.branding.store');
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
        Route::get('/inventory/batches/options', [BatchController::class, 'options'])->name('inventory.batches.options');
        Route::apiResource('inventory/batches', BatchController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('inventory/stock-adjustments', StockAdjustmentController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameter('stock-adjustments', 'adjustment');
        Route::get('/inventory/stock-movements', [StockMovementController::class, 'index'])->name('inventory.stock-movements.index');
        Route::post('/inventory/companies/quick', [InventoryMasterController::class, 'company'])->name('inventory.companies.quick');
        Route::post('/inventory/units/quick', [InventoryMasterController::class, 'unit'])->name('inventory.units.quick');
        Route::post('/inventory/categories/quick', [InventoryMasterController::class, 'category'])->name('inventory.categories.quick');
        Route::get('/inventory/masters/{master}', [InventoryMasterController::class, 'index'])->whereIn('master', ['companies', 'units', 'categories'])->name('inventory.masters.index');
        Route::post('/inventory/masters/{master}', [InventoryMasterController::class, 'store'])->whereIn('master', ['companies', 'units', 'categories'])->name('inventory.masters.store');
        Route::put('/inventory/masters/{master}/{id}', [InventoryMasterController::class, 'update'])->whereIn('master', ['companies', 'units', 'categories'])->name('inventory.masters.update');
        Route::delete('/inventory/masters/{master}/{id}', [InventoryMasterController::class, 'destroy'])->whereIn('master', ['companies', 'units', 'categories'])->name('inventory.masters.destroy');
        Route::post('/inventory/masters/{master}/{id}/restore', [InventoryMasterController::class, 'restore'])->whereIn('master', ['companies', 'units', 'categories'])->name('inventory.masters.restore');
        Route::apiResource('inventory/products', ProductController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('/suppliers/options', [SupplierController::class, 'options'])->name('suppliers.options');
        Route::apiResource('suppliers', SupplierController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/customers/options', [CustomerController::class, 'options'])->name('customers.options');
        Route::apiResource('customers', CustomerController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::apiResource('purchase/orders', PurchaseOrderController::class)->only(['index', 'store', 'show']);
        Route::post('purchase/orders/{order}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase.orders.approve');
        Route::post('purchase/orders/{order}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase.orders.receive');
        Route::post('purchase/orders/{order}/pay', [PurchaseOrderController::class, 'pay'])->name('purchase.orders.pay');
        Route::apiResource('purchases', PurchaseController::class)->only(['index', 'store']);
        Route::get('/purchase/returns/purchases', [PurchaseReturnController::class, 'purchases'])->name('purchase.returns.purchases');
        Route::get('/purchase/returns/purchases/{purchase}/items', [PurchaseReturnController::class, 'items'])->name('purchase.returns.purchase-items');
        Route::get('/purchase/returns/batches', [PurchaseReturnController::class, 'supplierBatches'])->name('purchase.returns.batches');
        Route::apiResource('purchase/returns', PurchaseReturnController::class)
            ->only(['index', 'show', 'store', 'update', 'destroy'])
            ->parameter('returns', 'purchaseReturn');

        Route::get('/sales/product-lookup', ProductLookupController::class)->name('sales.product-lookup');
        Route::apiResource('sales/invoices', SalesInvoiceController::class)->only(['index', 'store', 'show']);
        Route::get('/mr/performance', MrPerformanceController::class)->name('mr.performance');
        Route::get('/mr/options', [MedicalRepresentativeController::class, 'options'])->name('mr.options');
        Route::apiResource('mr/representatives', MedicalRepresentativeController::class)->only(['index', 'store', 'update', 'destroy'])->parameter('representatives', 'representative');
        Route::apiResource('mr/visits', RepresentativeVisitController::class)->only(['index', 'store', 'update', 'destroy'])->parameter('visits', 'visit');

        // Branches
        Route::get('/mr/branches/options', [BranchController::class, 'options'])->name('mr.branches.options');
        Route::apiResource('mr/branches', BranchController::class)->only(['index', 'store', 'update', 'destroy'])->parameter('branches', 'branch');

        // MR branch-level product sales breakdown
        Route::get('/mr/branch-sales', MrBranchSalesController::class)->name('mr.branch-sales');
        Route::apiResource('accounting/vouchers', VoucherController::class)->only(['index', 'store']);

        Route::get('/accounting/expenses', [ExpenseController::class, 'index'])->name('accounting.expenses.index');
        Route::post('/accounting/expenses', [ExpenseController::class, 'store'])->name('accounting.expenses.store');
        Route::delete('/accounting/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('accounting.expenses.destroy');

        Route::get('/accounting/payments', [PaymentController::class, 'index'])->name('accounting.payments.index');
        Route::post('/accounting/payments', [PaymentController::class, 'store'])->name('accounting.payments.store');
        Route::get('/accounting/payments/outstanding-bills', [PaymentController::class, 'outstandingBills'])->name('accounting.payments.outstanding-bills');

        Route::get('/sales/returns', [SalesReturnController::class, 'index'])->name('sales.returns.index');
        Route::post('/sales/returns', [SalesReturnController::class, 'store'])->name('sales.returns.store');
        Route::delete('/sales/returns/{salesReturn}', [SalesReturnController::class, 'destroy'])->name('sales.returns.destroy');
        Route::get('/sales/returns/invoice-options', [SalesReturnController::class, 'invoiceOptions'])->name('sales.returns.invoice-options');
        Route::get('/sales/returns/invoices/{invoice}/items', [SalesReturnController::class, 'invoiceItems'])->name('sales.returns.invoice-items');

        Route::get('/customers/{customer}/ledger', [CustomerLedgerController::class, 'show'])->name('customers.ledger');

        Route::get('/settings/dropdown-options', [DropdownOptionController::class, 'index'])->name('settings.dropdown-options.index');
        Route::post('/settings/dropdown-options', [DropdownOptionController::class, 'store'])->name('settings.dropdown-options.store');
        Route::put('/settings/dropdown-options/{dropdownOption}', [DropdownOptionController::class, 'update'])->name('settings.dropdown-options.update');
        Route::delete('/settings/dropdown-options/{dropdownOption}', [DropdownOptionController::class, 'destroy'])->name('settings.dropdown-options.destroy');

        Route::get('/settings/admin', [SettingsAdminController::class, 'show'])->name('settings.admin.show');
        Route::put('/settings/admin', [SettingsAdminController::class, 'update'])->name('settings.admin.update');
        Route::post('/settings/admin/test-mail', [SettingsAdminController::class, 'testMail'])->name('settings.admin.test-mail');

        Route::get('/settings/party-types', [PartyTypeController::class, 'index'])->name('settings.party-types.index');
        Route::post('/settings/party-types', [PartyTypeController::class, 'store'])->name('settings.party-types.store');
        Route::put('/settings/party-types/{partyType}', [PartyTypeController::class, 'update'])->name('settings.party-types.update');
        Route::delete('/settings/party-types/{partyType}', [PartyTypeController::class, 'destroy'])->name('settings.party-types.destroy');

        Route::get('/settings/supplier-types', [SupplierTypeController::class, 'index'])->name('settings.supplier-types.index');
        Route::post('/settings/supplier-types', [SupplierTypeController::class, 'store'])->name('settings.supplier-types.store');
        Route::put('/settings/supplier-types/{supplierType}', [SupplierTypeController::class, 'update'])->name('settings.supplier-types.update');
        Route::delete('/settings/supplier-types/{supplierType}', [SupplierTypeController::class, 'destroy'])->name('settings.supplier-types.destroy');
        Route::apiResource('settings/fiscal-years', FiscalYearController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('/reports/{report}', ReportController::class)->name('reports.show');

        Route::get('/imports/targets', [ImportWizardController::class, 'targets'])->name('imports.targets');
        Route::post('/imports/preview', [ImportWizardController::class, 'preview'])->name('imports.preview');
        Route::post('/imports/confirm', [ImportWizardController::class, 'confirm'])->name('imports.confirm');
        Route::get('/imports/{job}/rejected.csv', [ImportWizardController::class, 'rejected'])->name('imports.rejected');

        Route::get('/exports/inventory/masters/{master}/{format}', [ExportController::class, 'inventoryMaster'])
            ->whereIn('master', ['companies', 'units', 'categories'])
            ->whereIn('format', ['xlsx', 'pdf'])
            ->name('exports.inventory.masters');
        Route::get('/exports/inventory/products/{format}', [ExportController::class, 'inventoryProducts'])
            ->whereIn('format', ['xlsx', 'pdf'])
            ->name('exports.inventory.products');
        Route::get('/exports/inventory/batches/{format}', [ExportController::class, 'inventoryBatches'])
            ->whereIn('format', ['xlsx', 'pdf'])
            ->name('exports.inventory.batches');
    });

    Route::get('/purchases/{purchase}/print', [PurchaseController::class, 'print'])->name('purchases.print');
    Route::get('/purchases/{purchase}/pdf', [PurchaseController::class, 'pdf'])->name('purchases.pdf');
    Route::get('/purchase-returns/{purchaseReturn}/print', [PurchaseReturnController::class, 'print'])->name('purchase-returns.print');
    Route::get('/purchase-returns/{purchaseReturn}/pdf', [PurchaseReturnController::class, 'pdf'])->name('purchase-returns.pdf');
    Route::get('/sales/invoices/{invoice}/print', [SalesInvoiceController::class, 'print'])->name('sales.invoices.print');
    Route::get('/sales/invoices/{invoice}/pdf', [SalesInvoiceController::class, 'pdf'])->name('sales.invoices.pdf');

    Route::get('/admin/system/update-check', SpaController::class)->name('system.update-check');
    Route::get('/app/{any?}', SpaController::class)->where('any', '.*')->name('app');
});
