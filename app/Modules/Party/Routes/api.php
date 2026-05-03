<?php

use App\Modules\Party\Http\Controllers\CustomerController;
use App\Modules\Party\Http\Controllers\CustomerLedgerController;
use App\Modules\Party\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/suppliers/options', [SupplierController::class, 'options'])->name('suppliers.options');
Route::patch('/suppliers/{supplier}/status', [SupplierController::class, 'toggleStatus'])->name('suppliers.status');
Route::post('/suppliers/{id}/restore', [SupplierController::class, 'restore'])->name('suppliers.restore');
Route::apiResource('suppliers', SupplierController::class)->only(['index', 'store', 'update', 'destroy']);

Route::get('/customers/options', [CustomerController::class, 'options'])->name('customers.options');
Route::patch('/customers/{customer}/status', [CustomerController::class, 'toggleStatus'])->name('customers.status');
Route::post('/customers/{id}/restore', [CustomerController::class, 'restore'])->name('customers.restore');
Route::apiResource('customers', CustomerController::class)->only(['index', 'store', 'update', 'destroy']);
Route::get('/customers/{customer}/ledger', [CustomerLedgerController::class, 'show'])->name('customers.ledger');
