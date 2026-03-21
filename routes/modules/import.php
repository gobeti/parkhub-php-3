<?php

/**
 * Import module routes (api/v1).
 * Loaded only when MODULE_IMPORT=true.
 */

use App\Http\Controllers\Api\AdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:import', 'auth:sanctum', 'throttle:api', 'admin'])->prefix('admin')->group(function () {
    Route::post('/users/import', [AdminController::class, 'importUsers']);
});
