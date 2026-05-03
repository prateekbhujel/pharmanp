<?php

use App\Modules\Accounting\Http\Controllers\ExpenseController;
use App\Modules\Accounting\Http\Controllers\PaymentController;
use App\Modules\Accounting\Http\Controllers\VoucherController;
use Illuminate\Support\Facades\Route;

Route::apiResource('accounting/vouchers', VoucherController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

Route::get('/accounting/expenses', [ExpenseController::class, 'index'])->name('accounting.expenses.index');
Route::post('/accounting/expenses', [ExpenseController::class, 'store'])->name('accounting.expenses.store');
Route::delete('/accounting/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('accounting.expenses.destroy');

Route::get('/accounting/payments', [PaymentController::class, 'index'])->name('accounting.payments.index');
Route::post('/accounting/payments', [PaymentController::class, 'store'])->name('accounting.payments.store');
Route::get('/accounting/payments/outstanding-bills', [PaymentController::class, 'outstandingBills'])->name('accounting.payments.outstanding-bills');
Route::get('/accounting/payments/{payment}', [PaymentController::class, 'show'])->name('accounting.payments.show');
Route::delete('/accounting/payments/{payment}', [PaymentController::class, 'destroy'])->name('accounting.payments.destroy');
