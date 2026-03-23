<?php

/**
 * Compliance Reports module routes (api/v1).
 * Loaded only when MODULE_COMPLIANCE=true.
 */

use App\Http\Controllers\Api\ComplianceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:compliance', 'auth:sanctum', 'throttle:api', 'admin'])->group(function () {
    Route::get('/admin/compliance/report', [ComplianceController::class, 'report']);
    Route::get('/admin/compliance/data-map', [ComplianceController::class, 'dataMap']);
    Route::get('/admin/compliance/audit-export', [ComplianceController::class, 'auditExport']);
});
