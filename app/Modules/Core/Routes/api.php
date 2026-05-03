<?php

use App\Http\Controllers\CurrentUserController;
use App\Modules\Core\Http\Controllers\DashboardController;
use App\Modules\Core\Http\Controllers\GlobalSearchController;
use App\Modules\Core\Http\Controllers\ModuleCatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/me', CurrentUserController::class)->name('me');
Route::get('/modules', ModuleCatalogController::class)->name('modules.index');
Route::get('/dashboard/summary', DashboardController::class)->name('dashboard.summary');
Route::get('/search', GlobalSearchController::class)->name('search');
