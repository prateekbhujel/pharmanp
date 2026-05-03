<?php

use App\Modules\Setup\Http\Controllers\AreaController;
use App\Modules\Setup\Http\Controllers\BrandingController;
use App\Modules\Setup\Http\Controllers\DivisionController;
use App\Modules\Setup\Http\Controllers\DropdownOptionController;
use App\Modules\Setup\Http\Controllers\EmployeeController;
use App\Modules\Setup\Http\Controllers\FeatureCatalogController;
use App\Modules\Setup\Http\Controllers\FiscalYearController;
use App\Modules\Setup\Http\Controllers\PartyTypeController;
use App\Modules\Setup\Http\Controllers\ProfileController;
use App\Modules\Setup\Http\Controllers\RolePermissionController;
use App\Modules\Setup\Http\Controllers\SettingsAdminController;
use App\Modules\Setup\Http\Controllers\SupplierTypeController;
use App\Modules\Setup\Http\Controllers\TargetController;
use App\Modules\Setup\Http\Controllers\UserImpersonationController;
use App\Modules\Setup\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/setup/features', FeatureCatalogController::class)->name('setup.features');
Route::get('/setup/branding', [BrandingController::class, 'show'])->name('setup.branding.show');
Route::post('/setup/branding', [BrandingController::class, 'update'])->name('setup.branding.store');
Route::put('/setup/branding', [BrandingController::class, 'update'])->name('setup.branding.update');

Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

Route::get('/setup/roles', [RolePermissionController::class, 'index'])->name('setup.roles.index');
Route::post('/setup/roles', [RolePermissionController::class, 'store'])->name('setup.roles.store');
Route::put('/setup/roles/{role}', [RolePermissionController::class, 'update'])->name('setup.roles.update');
Route::delete('/setup/roles/{role}', [RolePermissionController::class, 'destroy'])->name('setup.roles.destroy');

Route::get('/setup/users', [UserManagementController::class, 'index'])->name('setup.users.index');
Route::post('/setup/users', [UserManagementController::class, 'store'])->name('setup.users.store');
Route::post('/setup/users/stop-impersonating', [UserImpersonationController::class, 'stop'])->name('setup.users.impersonate.stop');
Route::post('/setup/users/{user}/impersonate', [UserImpersonationController::class, 'start'])->name('setup.users.impersonate.start');
Route::put('/setup/users/{user}', [UserManagementController::class, 'update'])->name('setup.users.update');
Route::patch('/setup/users/{user}/status', [UserManagementController::class, 'toggleStatus'])->name('setup.users.status');
Route::delete('/setup/users/{user}', [UserManagementController::class, 'destroy'])->name('setup.users.destroy');

Route::get('/setup/areas/options', [AreaController::class, 'options'])->name('setup.areas.options');
Route::post('/setup/areas/{id}/restore', [AreaController::class, 'restore'])->name('setup.areas.restore');
Route::apiResource('setup/areas', AreaController::class)->only(['index', 'store', 'update', 'destroy']);

Route::get('/setup/divisions/options', [DivisionController::class, 'options'])->name('setup.divisions.options');
Route::post('/setup/divisions/{id}/restore', [DivisionController::class, 'restore'])->name('setup.divisions.restore');
Route::apiResource('setup/divisions', DivisionController::class)->only(['index', 'store', 'update', 'destroy']);

Route::get('/setup/employees/options', [EmployeeController::class, 'options'])->name('setup.employees.options');
Route::post('/setup/employees/{id}/restore', [EmployeeController::class, 'restore'])->name('setup.employees.restore');
Route::apiResource('setup/employees', EmployeeController::class)->only(['index', 'store', 'update', 'destroy']);

Route::post('/setup/targets/{id}/restore', [TargetController::class, 'restore'])->name('setup.targets.restore');
Route::apiResource('setup/targets', TargetController::class)->only(['index', 'store', 'update', 'destroy']);

Route::get('/settings/dropdown-options', [DropdownOptionController::class, 'index'])->name('settings.dropdown-options.index');
Route::post('/settings/dropdown-options', [DropdownOptionController::class, 'store'])->name('settings.dropdown-options.store');
Route::put('/settings/dropdown-options/{dropdownOption}', [DropdownOptionController::class, 'update'])->name('settings.dropdown-options.update');
Route::patch('/settings/dropdown-options/{dropdownOption}/status', [DropdownOptionController::class, 'toggleStatus'])->name('settings.dropdown-options.status');
Route::delete('/settings/dropdown-options/{dropdownOption}', [DropdownOptionController::class, 'destroy'])->name('settings.dropdown-options.destroy');

Route::get('/settings/admin', [SettingsAdminController::class, 'show'])->name('settings.admin.show');
Route::put('/settings/admin', [SettingsAdminController::class, 'update'])->name('settings.admin.update');
Route::post('/settings/admin/test-mail', [SettingsAdminController::class, 'testMail'])->name('settings.admin.test-mail');

Route::get('/settings/party-types', [PartyTypeController::class, 'index'])->name('settings.party-types.index');
Route::post('/settings/party-types', [PartyTypeController::class, 'store'])->name('settings.party-types.store');
Route::put('/settings/party-types/{partyType}', [PartyTypeController::class, 'update'])->name('settings.party-types.update');
Route::delete('/settings/party-types/{partyType}', [PartyTypeController::class, 'destroy'])->name('settings.party-types.destroy');

Route::get('/settings/supplier-types', [SupplierTypeController::class, 'index'])->name('settings.supplier-types.index');
Route::post('/settings/supplier-types', [SupplierTypeController::class, 'store'])->name('settings.supplier-types.store');
Route::put('/settings/supplier-types/{supplierType}', [SupplierTypeController::class, 'update'])->name('settings.supplier-types.update');
Route::delete('/settings/supplier-types/{supplierType}', [SupplierTypeController::class, 'destroy'])->name('settings.supplier-types.destroy');

Route::apiResource('settings/fiscal-years', FiscalYearController::class)->only(['index', 'store', 'update', 'destroy']);
