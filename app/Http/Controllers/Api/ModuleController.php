<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateModuleConfigRequest;
use App\Http\Requests\UpdateModuleRuntimeStateRequest;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator as JsonSchemaValidator;

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

    /**
     * PATCH /api/v1/admin/modules/{name}
     *
     * Admin-only endpoint to hot-toggle a module without a redeploy.
     * The module must be on the registry's runtime-toggleable allowlist
     * (security / payment / tenancy modules stay env-driven).
     *
     * Response codes:
     *   - 200: toggle persisted, returns the fresh ModuleInfo shape.
     *   - 404: module name unknown. `error.code = MODULE_NOT_FOUND`.
     *   - 409: module exists but is not runtime-toggleable.
     *     `error.code = MODULE_NOT_TOGGLEABLE`.
     *
     * Writes an `AuditLog` entry with the new state so an operator can
     * always reconstruct who flipped what, when — same pattern as the
     * Rust edition's `admin_modules::patch`.
     */
    public function updateRuntimeState(UpdateModuleRuntimeStateRequest $request, string $name): JsonResponse
    {
        if (ModuleRegistry::get($name) === null) {
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

        if (! ModuleRegistry::isToggleable($name)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'MODULE_NOT_TOGGLEABLE',
                    'message' => "Module '{$name}' is not runtime-toggleable; change its env flag to alter state.",
                ],
                'meta' => null,
            ], 409);
        }

        $runtimeEnabled = $request->boolean('runtime_enabled');
        ModuleRegistry::setRuntimeEnabled($name, $runtimeEnabled);

        AuditLog::log([
            'user_id' => $request->user()?->id,
            'username' => $request->user()?->username,
            'action' => 'module_runtime_toggled',
            'event_type' => 'admin',
            'details' => [
                'name' => $name,
                'new_state' => $runtimeEnabled,
            ],
            'ip_address' => $request->ip(),
            'target_type' => 'module',
            'target_id' => $name,
        ]);

        return response()->json([
            'success' => true,
            'data' => ModuleRegistry::get($name),
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * GET /api/v1/modules/{name}/config
     *
     * Return the module's JSON Schema plus the currently-persisted
     * values for each schema property. Shape:
     *   { schema: { ...JSON Schema 2020-12 object... },
     *     values: { default_theme: "dark", ... } }
     *
     * Only modules that declare a `config_schema` are editable via this
     * endpoint. Unknown module → 404; known but schema-less → 400. The
     * Rust edition returns the same codes so the shared frontend sees
     * one contract.
     */
    public function getConfig(Request $request, string $name): JsonResponse
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

        $schema = ModuleRegistry::configSchema($name);

        if ($schema === null) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'MODULE_HAS_NO_CONFIG_SCHEMA',
                    'message' => "Module '{$name}' does not expose a config schema; it is env-only.",
                ],
                'meta' => null,
            ], 400);
        }

        $values = $this->readPersistedValues($name, $schema);

        return response()->json([
            'success' => true,
            'data' => [
                'schema' => $schema,
                'values' => (object) $values,
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * PATCH /api/v1/modules/{name}/config
     *
     * Validate the payload against the module's JSON Schema via
     * opis/json-schema. On success, persist each property to the
     * `settings` table under `module.{name}.config.{key}` and emit an
     * AuditLog entry. On failure, return 422 with `{error.code:
     * CONFIG_VALIDATION_FAILED, details: [...]}` — the Rust edition
     * surfaces the exact same error envelope.
     *
     * Shape of the 200 response mirrors `getConfig`, so the frontend
     * can do a single-call round-trip without re-fetching.
     */
    public function updateConfig(UpdateModuleConfigRequest $request, string $name): JsonResponse
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

        $schema = ModuleRegistry::configSchema($name);

        if ($schema === null) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'MODULE_HAS_NO_CONFIG_SCHEMA',
                    'message' => "Module '{$name}' does not expose a config schema; it is env-only.",
                ],
                'meta' => null,
            ], 400);
        }

        /** @var array<string, mixed> $values */
        $values = $request->input('values', []);

        // opis/json-schema wants the data and the schema as stdClass /
        // scalar trees (it treats assoc-arrays as JSON arrays, not
        // objects). json_decode(json_encode(...)) is the canonical way
        // to normalise without hand-rolling a recursive walker — the
        // registry schema is tiny so the extra encode cost is trivial.
        $dataObj = json_decode((string) json_encode((object) $values));
        $schemaObj = json_decode((string) json_encode($schema));

        // Positional args — the opis constructor uses snake_case names
        // internally (`$max_errors`, `$stop_at_first_error`) so named
        // params trip a TypeError on PHP 8.4. Loader=null, 20 errors,
        // don't short-circuit on first hit so the 422 body lists every
        // problem the admin needs to fix.
        $validator = new JsonSchemaValidator(null, 20, false);
        $result = $validator->validate($dataObj, $schemaObj);

        if ($result->hasError()) {
            $formatter = new ErrorFormatter;
            /** @var array<string, list<string>> $details */
            $details = $formatter->formatKeyed($result->error());

            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'CONFIG_VALIDATION_FAILED',
                    'message' => 'Config payload failed schema validation.',
                    'details' => $details,
                ],
                'meta' => null,
            ], 422);
        }

        // Persist each top-level property independently so the settings
        // table stores one row per key — matches the key-value contract
        // the rest of the admin surface already uses (e.g. runtime
        // toggles under `module.{name}.runtime_enabled`).
        $keysChanged = [];
        foreach ($values as $key => $value) {
            Setting::set(
                ModuleRegistry::configSettingKey($name, (string) $key),
                json_encode($value),
            );
            $keysChanged[] = (string) $key;
        }

        AuditLog::log([
            'user_id' => $request->user()?->id,
            'username' => $request->user()?->username,
            'action' => 'module_config_updated',
            'event_type' => 'admin',
            'details' => [
                'name' => $name,
                'keys_changed' => $keysChanged,
            ],
            'ip_address' => $request->ip(),
            'target_type' => 'module',
            'target_id' => $name,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'schema' => $schema,
                'values' => (object) $this->readPersistedValues($name, $schema),
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * Read the currently-persisted value for each property declared in
     * the schema. Missing settings are simply absent from the returned
     * map — the frontend treats absence as "use schema default / leave
     * field empty". Stored values come back through `json_decode` so
     * booleans, integers, and strings survive the round-trip.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function readPersistedValues(string $name, array $schema): array
    {
        /** @var array<string, mixed> $properties */
        $properties = $schema['properties'] ?? [];

        $values = [];
        foreach (array_keys($properties) as $key) {
            $raw = Setting::get(ModuleRegistry::configSettingKey($name, (string) $key));
            if ($raw === null) {
                continue;
            }

            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            $values[$key] = $decoded;
        }

        return $values;
    }
}
