<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ModuleRegistry;
use Illuminate\Http\JsonResponse;

/**
 * Modules API — backwards-compatible envelope plus enriched metadata.
 *
 * Historic contract: `GET /api/v1/modules` returned `{modules: {name: bool}}`.
 * That map is still there. The new `module_info` array carries the
 * ModuleInfo-shaped records the shared frontend (parkhub-web) uses to
 * render the Modules Dashboard and to auto-register command-palette
 * entries per active module.
 *
 * Responses are pre-shaped with `success`/`data`/`error` so the global
 * ApiResponseWrapper skips re-wrapping — mirrors the pattern used by
 * EVChargingController and PluginController. The `data` payload is the
 * `{modules, module_info, version}` envelope the Rust backend also
 * ships, so the shared parkhub-web client sees the same shape from
 * either backend.
 */
class ModuleController extends Controller
{
    /**
     * GET /api/v1/modules
     *
     * Response payload (inside the global wrapper's `data` key):
     *   {
     *     "modules":     { "bookings": true, "stripe": false, ... },
     *     "module_info": [ { name, category, description, ... }, ... ],
     *     "version":     "4.12.0"
     *   }
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'modules' => ModuleRegistry::enabledMap(),
                'module_info' => ModuleRegistry::all(),
                'version' => SystemController::appVersion(),
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * GET /api/v1/modules/{name}
     *
     * Returns the single ModuleInfo-shaped record or a 404 when the
     * slug is unknown.
     */
    public function show(string $name): JsonResponse
    {
        $info = ModuleRegistry::get($name);

        if ($info === null) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'MODULE_NOT_FOUND',
                    'message' => "Unknown module '{$name}'",
                ],
                'meta' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $info,
            'error' => null,
            'meta' => null,
        ]);
    }
}
