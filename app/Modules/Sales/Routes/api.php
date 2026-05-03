<?php

use App\Modules\Sales\Http\Controllers\ProductLookupController;
use App\Modules\Sales\Http\Controllers\SalesInvoiceController;
use App\Modules\Sales\Http\Controllers\SalesReturnController;
use Illuminate\Support\Facades\Route;

Route::get('/sales/product-lookup', ProductLookupController::class)->name('sales.product-lookup');
Route::apiResource('sales/invoices', SalesInvoiceController::class)->only(['index', 'store', 'show']);
Route::get('/sales/invoices/{invoice}/items', [SalesInvoiceController::class, 'items'])->name('sales.invoices.items');
Route::get('/sales/invoices/{invoice}/returns', [SalesInvoiceController::class, 'returns'])->name('sales.invoices.returns');
Route::patch('/sales/invoices/{invoice}/payment', [SalesInvoiceController::class, 'updatePayment'])->name('sales.invoices.payment');

Route::get('/sales/returns/invoice-options', [SalesReturnController::class, 'invoiceOptions'])->name('sales.returns.invoice-options');
Route::get('/sales/returns/invoices/{invoice}/items', [SalesReturnController::class, 'invoiceItems'])->name('sales.returns.invoice-items');
Route::get('/sales/returns', [SalesReturnController::class, 'index'])->name('sales.returns.index');
Route::post('/sales/returns', [SalesReturnController::class, 'store'])->name('sales.returns.store');
Route::get('/sales/returns/{salesReturn}', [SalesReturnController::class, 'show'])->name('sales.returns.show');
Route::put('/sales/returns/{salesReturn}', [SalesReturnController::class, 'update'])->name('sales.returns.update');
Route::delete('/sales/returns/{salesReturn}', [SalesReturnController::class, 'destroy'])->name('sales.returns.destroy');
