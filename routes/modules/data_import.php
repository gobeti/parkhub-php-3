<?php

/**
 * Data Import module routes (api/v1).
 * Loaded only when MODULE_DATA_IMPORT=true.
 */

use App\Http\Controllers\Api\DataImportExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:data_import', 'auth:sanctum', 'throttle:api', 'admin'])->group(function () {
    Route::post('admin/import/users', [DataImportExportController::class, 'importUsers']);
    Route::post('admin/import/lots', [DataImportExportController::class, 'importLots']);
    Route::get('admin/data/export/users', [DataImportExportController::class, 'exportUsers']);
    Route::get('admin/data/export/lots', [DataImportExportController::class, 'exportLots']);
    Route::get('admin/data/export/bookings', [DataImportExportController::class, 'exportBookings']);
});
