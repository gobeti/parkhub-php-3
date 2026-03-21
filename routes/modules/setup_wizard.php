<?php

/**
 * Setup Wizard module routes (api/v1).
 * Loaded only when MODULE_SETUP_WIZARD=true.
 */

use App\Http\Controllers\Api\SetupController;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::middleware('module:setup_wizard')->group(function () {
    Route::get('/setup/status', [SetupController::class, 'status']);
    Route::post('/setup', [SetupController::class, 'init']);
    Route::middleware('throttle:setup')->group(function () {
        Route::post('/setup/change-password', function (Request $request) {
            if (Setting::get('setup_completed') === 'true') {
                return response()->json(['success' => false, 'error' => ['code' => 'SETUP_COMPLETED', 'message' => 'Setup has already been completed']], 403);
            }
            $request->validate(['current_password' => 'required', 'new_password' => 'required|min:8']);
            $admin = User::where('role', 'admin')->first();
            if (! $admin || ! Hash::check($request->current_password, $admin->password)) {
                return response()->json(['success' => false, 'error' => ['code' => 'INVALID_PASSWORD', 'message' => 'Current password is incorrect']], 401);
            }
            $admin->password = Hash::make($request->new_password);
            $admin->save();
            Setting::set('needs_password_change', 'false');
            $token = $admin->createToken('auth-token');

            return response()->json(['success' => true, 'data' => [
                'user' => $admin,
                'tokens' => ['access_token' => $token->plainTextToken, 'token_type' => 'Bearer', 'expires_at' => now()->addDays(7)->toISOString()],
            ]]);
        });
        Route::post('/setup/complete', function (Request $request) {
            if (Setting::get('setup_completed') === 'true') {
                return response()->json(['success' => false, 'error' => ['code' => 'SETUP_COMPLETED', 'message' => 'Setup has already been completed']], 403);
            }
            Setting::set('setup_completed', 'true');
            if ($request->company_name) {
                Setting::set('company_name', $request->company_name);
            }
            if ($request->use_case) {
                Setting::set('use_case', $request->use_case);
            }

            return response()->json(['success' => true, 'data' => ['message' => 'Setup completed']]);
        });
    });
});
