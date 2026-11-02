<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FoundationController;
use App\Http\Controllers\MasterDataController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware(['web', 'throttle:login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout'])->middleware('web');
        Route::get('dashboard', DashboardController::class);
        Route::apiResource('employees', EmployeeController::class);
        Route::get('{module}', [MasterDataController::class, 'index'])->whereIn('module', ['divisions', 'units', 'designations']);
        Route::post('{module}', [MasterDataController::class, 'store'])->whereIn('module', ['divisions', 'units', 'designations']);
        Route::get('{module}/{record}', [MasterDataController::class, 'show'])->whereIn('module', ['divisions', 'units', 'designations'])->whereNumber('record');
        Route::put('{module}/{record}', [MasterDataController::class, 'update'])->whereIn('module', ['divisions', 'units', 'designations'])->whereNumber('record');
        Route::delete('{module}/{record}', [MasterDataController::class, 'destroy'])->whereIn('module', ['divisions', 'units', 'designations'])->whereNumber('record');
        Route::get('attendance', [FoundationController::class, 'attendance']);
        Route::post('attendance/import', [FoundationController::class, 'import']);
        Route::get('leaves', [FoundationController::class, 'leaves']);
    });
});
