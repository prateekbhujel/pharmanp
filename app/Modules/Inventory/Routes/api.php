<?php

use App\Modules\Inventory\Http\Controllers\BatchController;
use App\Modules\Inventory\Http\Controllers\InventoryMasterController;
use App\Modules\Inventory\Http\Controllers\ProductController;
use App\Modules\Inventory\Http\Controllers\StockAdjustmentController;
use App\Modules\Inventory\Http\Controllers\StockMovementController;
use Illuminate\Support\Facades\Route;

Route::get('/inventory/products/meta', [ProductController::class, 'meta'])->name('inventory.products.meta');
Route::get('/inventory/batches/options', [BatchController::class, 'options'])->name('inventory.batches.options');
Route::apiResource('inventory/batches', BatchController::class)->only(['index', 'store', 'update', 'destroy']);

Route::apiResource('inventory/stock-adjustments', StockAdjustmentController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->parameter('stock-adjustments', 'adjustment');
Route::get('/inventory/stock-movements', [StockMovementController::class, 'index'])->name('inventory.stock-movements.index');

Route::post('/inventory/companies/quick', [InventoryMasterController::class, 'company'])->name('inventory.companies.quick');
Route::post('/inventory/units/quick', [InventoryMasterController::class, 'unit'])->name('inventory.units.quick');
Route::get('/inventory/masters/{master}', [InventoryMasterController::class, 'index'])->whereIn('master', ['companies', 'units'])->name('inventory.masters.index');
Route::post('/inventory/masters/{master}', [InventoryMasterController::class, 'store'])->whereIn('master', ['companies', 'units'])->name('inventory.masters.store');
Route::put('/inventory/masters/{master}/{id}', [InventoryMasterController::class, 'update'])->whereIn('master', ['companies', 'units'])->name('inventory.masters.update');
Route::patch('/inventory/masters/{master}/{id}/status', [InventoryMasterController::class, 'toggleStatus'])->whereIn('master', ['companies', 'units'])->name('inventory.masters.status');
Route::delete('/inventory/masters/{master}/{id}', [InventoryMasterController::class, 'destroy'])->whereIn('master', ['companies', 'units'])->name('inventory.masters.destroy');
Route::post('/inventory/masters/{master}/{id}/restore', [InventoryMasterController::class, 'restore'])->whereIn('master', ['companies', 'units'])->name('inventory.masters.restore');

Route::post('/inventory/products/{id}/restore', [ProductController::class, 'restore'])->name('inventory.products.restore');
Route::apiResource('inventory/products', ProductController::class)->only(['index', 'store', 'update', 'destroy']);
Route::patch('/inventory/products/{product}/status', [ProductController::class, 'toggleStatus'])->name('inventory.products.status');
