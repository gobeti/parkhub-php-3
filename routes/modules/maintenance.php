<?php

/**
 * Maintenance Scheduling module routes (api/v1).
 * Loaded only when MODULE_MAINTENANCE=true.
 */

use App\Http\Controllers\Api\MaintenanceController;
use Illuminate\Support\Facades\Route;

// Public — active maintenance windows (authenticated)
Route::middleware(['module:maintenance', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/maintenance/active', [MaintenanceController::class, 'active']);
});

// Admin CRUD
Route::middleware(['module:maintenance', 'auth:sanctum', 'throttle:api', 'admin'])->prefix('admin/maintenance')->group(function () {
    Route::get('/', [MaintenanceController::class, 'index']);
    Route::post('/', [MaintenanceController::class, 'store']);
    Route::put('/{id}', [MaintenanceController::class, 'update']);
    Route::delete('/{id}', [MaintenanceController::class, 'destroy']);
});
