<?php

/**
 * Data Export module routes (api/v1).
 * Loaded only when MODULE_DATA_EXPORT=true.
 */

use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:data_export', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/users/me/export', [UserController::class, 'export']);
    Route::get('/user/export', [UserController::class, 'exportData']);
    Route::get('/user/calendar.ics', [UserController::class, 'calendarExport']);
});
