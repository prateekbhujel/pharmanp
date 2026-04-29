<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\SpaController;
use App\Modules\Accounting\Http\Controllers\PaymentController;
use App\Modules\Party\Http\Controllers\CustomerLedgerController;
use App\Modules\Purchase\Http\Controllers\PurchaseController;
use App\Modules\Purchase\Http\Controllers\PurchaseReturnController;
use App\Modules\Sales\Http\Controllers\SalesInvoiceController;
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
    require __DIR__.'/api_v1.php';

    Route::get('/purchases/{purchase}/print', [PurchaseController::class, 'print'])->name('purchases.print');
    Route::get('/purchases/{purchase}/pdf', [PurchaseController::class, 'pdf'])->name('purchases.pdf');
    Route::get('/purchase-returns/{purchaseReturn}/print', [PurchaseReturnController::class, 'print'])->name('purchase-returns.print');
    Route::get('/purchase-returns/{purchaseReturn}/pdf', [PurchaseReturnController::class, 'pdf'])->name('purchase-returns.pdf');
    Route::get('/sales/invoices/{invoice}/print', [SalesInvoiceController::class, 'print'])->name('sales.invoices.print');
    Route::get('/sales/invoices/{invoice}/pdf', [SalesInvoiceController::class, 'pdf'])->name('sales.invoices.pdf');
    Route::get('/payments/{payment}/print', [PaymentController::class, 'print'])->name('payments.print');
    Route::get('/payments/{payment}/pdf', [PaymentController::class, 'pdf'])->name('payments.pdf');
    Route::get('/customers/{customer}/ledger/print', [CustomerLedgerController::class, 'print'])->name('customers.ledger.print');
    Route::get('/customers/{customer}/ledger/pdf', [CustomerLedgerController::class, 'pdf'])->name('customers.ledger.pdf');

    Route::get('/app/{any?}', SpaController::class)->where('any', '.*')->name('app');
});
