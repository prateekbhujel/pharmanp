<?php

use App\Http\Controllers\CurrentUserController;
use App\Modules\Core\Http\Controllers\ApiAuthController;
use App\Modules\Core\Http\Controllers\DashboardController;
use App\Modules\Core\Http\Controllers\GlobalSearchController;
use App\Modules\Core\Http\Controllers\ModuleCatalogController;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [ApiAuthController::class, 'login'])
    ->withoutMiddleware('pharmanp.api')
    ->middleware('throttle:6,1')
    ->name('auth.login');
Route::post('/auth/token', [ApiAuthController::class, 'token'])
    ->middleware([StartSession::class])
    ->name('auth.token');
Route::post('/auth/logout', [ApiAuthController::class, 'logout'])
    ->middleware([StartSession::class])
    ->name('auth.logout');

Route::get('/me', CurrentUserController::class)->middleware([StartSession::class])->name('me');
Route::get('/modules', ModuleCatalogController::class)->name('modules.index');
Route::get('/dashboard/summary', DashboardController::class)->name('dashboard.summary');
Route::get('/search', GlobalSearchController::class)->name('search');
