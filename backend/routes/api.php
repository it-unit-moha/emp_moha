<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserAdminController;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware(['web', 'throttle:login']);
    Route::middleware(['auth:sanctum', EnsureActiveAccount::class])->group(function () {
        Route::get('user', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout'])->middleware('web');
        Route::get('dashboard', DashboardController::class);
        Route::apiResource('employees', EmployeeController::class);
        Route::get('{module}', [MasterDataController::class, 'index'])->whereIn('module', ['divisions', 'units', 'designations']);
        Route::post('{module}', [MasterDataController::class, 'store'])->whereIn('module', ['divisions', 'units', 'designations']);
        Route::get('{module}/{record}', [MasterDataController::class, 'show'])->whereIn('module', ['divisions', 'units', 'designations'])->whereNumber('record');
        Route::put('{module}/{record}', [MasterDataController::class, 'update'])->whereIn('module', ['divisions', 'units', 'designations'])->whereNumber('record');
        Route::delete('{module}/{record}', [MasterDataController::class, 'destroy'])->whereIn('module', ['divisions', 'units', 'designations'])->whereNumber('record');
        Route::get('reports/pdf', [ReportController::class, 'download']);
        Route::get('reports/snapshots', [ReportController::class, 'snapshots']);
        Route::get('reports/snapshots/{id}', [ReportController::class, 'snapshot'])->whereNumber('id');
        Route::get('users', [UserAdminController::class, 'index']);
        Route::post('users', [UserAdminController::class, 'save']);
        Route::put('users/{id}', [UserAdminController::class, 'save'])->whereNumber('id');
        Route::get('workflow', [OperationsController::class, 'overview']);
        Route::post('workflow/initialize', [OperationsController::class, 'initialize']);
        Route::get('attendance/template', [OperationsController::class, 'template']);
        Route::get('attendance', [OperationsController::class, 'attendance']);
        Route::post('attendance/import', [OperationsController::class, 'import']);
        Route::post('leaves', [OperationsController::class, 'requestLeave']);
        Route::post('leaves/{id}/review', [OperationsController::class, 'review'])->whereNumber('id');
        Route::post('leave-balances/adjust', [OperationsController::class, 'adjust']);
        Route::post('holidays', [OperationsController::class, 'holiday']);
        Route::delete('holidays/{id}', [OperationsController::class, 'deleteHoliday'])->whereNumber('id');
        Route::post('settings/penalties', [OperationsController::class, 'penaltySettings']);
        Route::get('audit', [OperationsController::class, 'audit']);
        Route::get('leaves', [OperationsController::class, 'leaves']);
    });
});
