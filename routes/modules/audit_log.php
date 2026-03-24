<?php

/**
 * Audit Log module routes (api/v1).
 * Loaded only when MODULE_AUDIT_LOG=true.
 */

use App\Http\Controllers\Api\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:audit_log', 'auth:sanctum', 'throttle:api', 'admin'])->prefix('admin/audit-log')->group(function () {
    Route::get('/', [AuditLogController::class, 'index']);
    Route::get('/export', [AuditLogController::class, 'export']);
    Route::get('/export/enhanced', [AuditLogController::class, 'exportEnhanced'])->middleware('module:audit_export');
});
