<?php

use App\Core\Services\InstallationService;
use App\Http\Controllers\SpaController;
use App\Modules\Accounting\Http\Controllers\PaymentController;
use App\Modules\Core\Http\Controllers\OpenApiController;
use App\Modules\Party\Http\Controllers\CustomerLedgerController;
use App\Modules\Purchase\Http\Controllers\PurchaseController;
use App\Modules\Purchase\Http\Controllers\PurchaseReturnController;
use App\Modules\Sales\Http\Controllers\SalesInvoiceController;
use App\Modules\Sales\Http\Controllers\SalesReturnController;
use App\Modules\Setup\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::get('/api/v1/openapi.json', OpenApiController::class)
    ->middleware('installed')
    ->name('api.openapi');

Route::get('/docs/api-docs.json', OpenApiController::class)
    ->middleware('installed')
    ->name('api.docs.json');

Route::view('/api-docs', 'api-docs')
    ->middleware('installed')
    ->name('api.docs');

Route::view('/api/documentation', 'api-docs')
    ->middleware('installed')
    ->name('api.documentation');

Route::get('/', function () {
    if (! app(InstallationService::class)->installed()) {
        return redirect()->route('setup.show');
    }

    return redirect()->route('app');
})->name('home');

Route::middleware('not_installed')->group(function () {
    Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
    Route::get('/setup/status', [SetupController::class, 'status'])->name('setup.status');
    Route::post('/setup/complete', [SetupController::class, 'complete'])->name('setup.complete');
});

Route::redirect('/login', '/app')->middleware('installed')->name('login');

Route::get('/app/{any?}', SpaController::class)
    ->middleware(['installed'])
    ->where('any', '.*')
    ->name('app');

Route::middleware(['installed', 'pharmanp.api'])->group(function () {
    Route::get('/purchases/{purchase}/print', [PurchaseController::class, 'print'])->name('purchases.print');
    Route::get('/purchases/{purchase}/pdf', [PurchaseController::class, 'pdf'])->name('purchases.pdf');
    Route::get('/purchase-returns/{purchaseReturn}/print', [PurchaseReturnController::class, 'print'])->name('purchase-returns.print');
    Route::get('/purchase-returns/{purchaseReturn}/pdf', [PurchaseReturnController::class, 'pdf'])->name('purchase-returns.pdf');
    Route::get('/sales/invoices/{invoice}/print', [SalesInvoiceController::class, 'print'])->name('sales.invoices.print');
    Route::get('/sales/invoices/{invoice}/pdf', [SalesInvoiceController::class, 'pdf'])->name('sales.invoices.pdf');
    Route::get('/sales/returns/{salesReturn}/print', [SalesReturnController::class, 'print'])->name('sales.returns.print');
    Route::get('/sales/returns/{salesReturn}/pdf', [SalesReturnController::class, 'pdf'])->name('sales.returns.pdf');
    Route::get('/payments/{payment}/print', [PaymentController::class, 'print'])->name('payments.print');
    Route::get('/payments/{payment}/pdf', [PaymentController::class, 'pdf'])->name('payments.pdf');
    Route::get('/customers/{customer}/ledger/print', [CustomerLedgerController::class, 'print'])->name('customers.ledger.print');
    Route::get('/customers/{customer}/ledger/pdf', [CustomerLedgerController::class, 'pdf'])->name('customers.ledger.pdf');

});
