<?php

/**
 * Multi-Tenant module routes (api/v1).
 * Loaded only when MODULE_MULTI_TENANT=true.
 */

use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:multi_tenant', 'auth:sanctum', 'throttle:api', 'admin'])->prefix('admin/tenants')->group(function () {
    Route::get('/', [TenantController::class, 'index']);
    Route::post('/', [TenantController::class, 'store']);
    Route::put('/{id}', [TenantController::class, 'update']);
});
