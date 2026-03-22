<?php

/**
 * Cost Center Billing module routes (api/v1).
 * Loaded only when MODULE_COST_CENTER=true.
 */

use App\Http\Controllers\Api\BillingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:cost_center', 'auth:sanctum', 'throttle:api', 'admin'])->prefix('admin/billing')->group(function () {
    Route::get('/by-cost-center', [BillingController::class, 'byCostCenter']);
    Route::get('/by-department', [BillingController::class, 'byDepartment']);
    Route::get('/export', [BillingController::class, 'export']);
    Route::post('/allocate', [BillingController::class, 'allocate']);
});
