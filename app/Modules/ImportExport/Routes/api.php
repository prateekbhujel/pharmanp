<?php

use App\Modules\ImportExport\Http\Controllers\ExportController;
use App\Modules\ImportExport\Http\Controllers\ImportWizardController;
use App\Modules\ImportExport\Http\Controllers\PurchaseOcrController;
use Illuminate\Support\Facades\Route;

Route::get('/imports/targets', [ImportWizardController::class, 'targets'])->name('imports.targets');
Route::get('/imports/targets/{target}/sample', [ImportWizardController::class, 'sample'])->name('imports.sample');
Route::post('/imports/preview', [ImportWizardController::class, 'preview'])->name('imports.preview');
Route::post('/imports/confirm', [ImportWizardController::class, 'confirm'])->name('imports.confirm');
Route::post('/imports/ocr/extract', [PurchaseOcrController::class, 'extract'])->name('imports.ocr.extract');
Route::post('/imports/ocr/draft-purchase', [PurchaseOcrController::class, 'draftPurchase'])->name('imports.ocr.draft-purchase');
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
Route::get('/exports/{dataset}/{format}', [ExportController::class, 'dataset'])
    ->whereIn('dataset', ['suppliers', 'customers', 'sales-invoices', 'purchases', 'purchase-orders', 'payments', 'expenses', 'users', 'account-tree'])
    ->whereIn('format', ['xlsx', 'pdf'])
    ->name('exports.dataset');
