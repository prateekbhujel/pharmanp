<?php

use App\Modules\Reports\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/reports/{report}/export/{format}', [ReportController::class, 'export'])
    ->whereIn('format', ['xlsx', 'pdf'])
    ->name('reports.export');
Route::get('/reports/{report}', ReportController::class)->name('reports.show');
