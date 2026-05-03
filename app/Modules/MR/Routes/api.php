<?php

use App\Modules\MR\Http\Controllers\BranchController;
use App\Modules\MR\Http\Controllers\MedicalRepresentativeController;
use App\Modules\MR\Http\Controllers\MrBranchSalesController;
use App\Modules\MR\Http\Controllers\MrPerformanceController;
use App\Modules\MR\Http\Controllers\RepresentativeVisitController;
use Illuminate\Support\Facades\Route;

Route::get('/mr/performance', MrPerformanceController::class)->name('mr.performance');
Route::get('/mr/options', [MedicalRepresentativeController::class, 'options'])->name('mr.options');
Route::apiResource('mr/representatives', MedicalRepresentativeController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->parameter('representatives', 'representative');

Route::apiResource('mr/visits', RepresentativeVisitController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->parameter('visits', 'visit');

Route::get('/mr/branches/options', [BranchController::class, 'options'])->name('mr.branches.options');
Route::patch('/mr/branches/{branch}/status', [BranchController::class, 'toggleStatus'])->name('mr.branches.status');
Route::post('/mr/branches/{id}/restore', [BranchController::class, 'restore'])->name('mr.branches.restore');
Route::apiResource('mr/branches', BranchController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->parameter('branches', 'branch');

Route::get('/mr/branch-sales', MrBranchSalesController::class)->name('mr.branch-sales');
