<?php

/**
 * RBAC module routes (api/v1).
 * Loaded only when MODULE_RBAC=true.
 */

use App\Http\Controllers\Api\RBACController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:rbac', 'auth:sanctum', 'throttle:api', 'admin'])->prefix('admin')->group(function () {
    Route::get('/roles', [RBACController::class, 'listRoles']);
    Route::post('/roles', [RBACController::class, 'createRole']);
    Route::put('/roles/{id}', [RBACController::class, 'updateRole']);
    Route::delete('/roles/{id}', [RBACController::class, 'deleteRole']);
    Route::get('/permissions', [RBACController::class, 'listPermissions']);
    Route::get('/users/{userId}/roles', [RBACController::class, 'getUserRoles']);
    Route::put('/users/{userId}/roles', [RBACController::class, 'assignRoles']);
});
