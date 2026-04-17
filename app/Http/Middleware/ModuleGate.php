<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runtime-aware module gate.
 *
 * v1 (`CheckModule`) rejected requests when `config('modules.{name}')`
 * was false. That's still the right default for env-only modules
 * (security, payments, tenancy) — those aren't on the runtime
 * allowlist and the env flag is the sole truth.
 *
 * v2 adds the admin-controllable layer: for any slug on
 * `ModuleRegistry::RUNTIME_TOGGLEABLE`, we consult the registry's
 * materialized `runtime_enabled` field — which folds in the Setting
 * override persisted by PATCH /admin/modules/{name}. For unknown
 * slugs (legacy modules that haven't been added to the registry
 * yet) we fall back to the old `config('modules.{name}')` check so
 * no existing route regresses.
 *
 * Registered as the `module` alias so the existing
 * `Route::middleware('module:map')` call sites pick up the new
 * behaviour automatically.
 */
class ModuleGate
{
    public function handle(Request $request, Closure $next, string $moduleName): Response
    {
        $info = ModuleRegistry::get($moduleName);

        // Module is in the registry → runtime_enabled is authoritative.
        if ($info !== null) {
            if (! $info['runtime_enabled']) {
                return response()->json([
                    'error' => 'MODULE_DISABLED',
                    'message' => 'Module is currently disabled',
                ], 404);
            }

            return $next($request);
        }

        // Legacy fallback: slug isn't in the registry yet, honour the
        // env/config flag like v1 did.
        if (! config("modules.{$moduleName}")) {
            return response()->json([
                'error' => 'MODULE_DISABLED',
                'message' => 'Module is currently disabled',
            ], 404);
        }

        return $next($request);
    }
}
