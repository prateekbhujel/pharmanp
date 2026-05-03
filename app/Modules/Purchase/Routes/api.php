<?php

use App\Modules\Purchase\Http\Controllers\PurchaseController;
use App\Modules\Purchase\Http\Controllers\PurchaseOrderController;
use App\Modules\Purchase\Http\Controllers\PurchaseReturnController;
use Illuminate\Support\Facades\Route;

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
