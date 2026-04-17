<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\ModuleRegistry;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator as JsonSchemaValidator;

/**
 * Owns the runtime-state toggling and JSON-schema-validated config
 * persistence extracted from ModuleController (T-1742, pass 4).
 *
 * Pure extraction — the registry-existence check, toggleable allowlist
 * check, opis/json-schema 2020-12 validation flow, per-key `settings`
 * row write and the `module_runtime_toggled` / `module_config_updated`
 * AuditLog entries all match the previous inline controller
 * implementation. Controllers stay responsible for HTTP shaping — they
 * literally build their `{success, data, error, meta}` envelopes so
 * Scramble can introspect each path's OpenAPI shape.
 */
final class ModuleConfigurationService
{
    /**
     * Flip the runtime enabled state for a module. Returns the failure
     * result when the module is unknown or not on the toggleable
     * allowlist; otherwise writes the setting + AuditLog.
     *
     * @param  array<string, mixed>  $actor  ['user_id' => ?string, 'username' => ?string, 'ip_address' => ?string]
     */
    public function toggleRuntimeState(string $name, bool $runtimeEnabled, array $actor): ModuleConfigurationResult
    {
        if (ModuleRegistry::get($name) === null) {
            return ModuleConfigurationResult::notFound($name);
        }

        if (! ModuleRegistry::isToggleable($name)) {
            return ModuleConfigurationResult::notToggleable($name);
        }

        ModuleRegistry::setRuntimeEnabled($name, $runtimeEnabled);

        AuditLog::log([
            'user_id' => $actor['user_id'] ?? null,
            'username' => $actor['username'] ?? null,
            'action' => 'module_runtime_toggled',
            'event_type' => 'admin',
            'details' => [
                'name' => $name,
                'new_state' => $runtimeEnabled,
            ],
            'ip_address' => $actor['ip_address'] ?? null,
            'target_type' => 'module',
            'target_id' => $name,
        ]);

        return ModuleConfigurationResult::ok();
    }

    /**
     * Validate $values against the passed JSON Schema and, on success,
     * persist each top-level property under `module.{name}.config.{key}`
     * and emit an AuditLog entry. On validation failure, return a
     * `validationFailed` result carrying the formatted error keyed map.
     *
     * Schema is passed in (not re-fetched) so the controller stays the
     * single source of truth for the "no schema declared" 400 path;
     * this keeps Scramble's per-path response shape inference clean.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $actor  ['user_id' => ?string, 'username' => ?string, 'ip_address' => ?string]
     */
    public function updateConfig(string $name, array $schema, array $values, array $actor): ModuleConfigurationResult
    {
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

            return ModuleConfigurationResult::validationFailed($details);
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
            'user_id' => $actor['user_id'] ?? null,
            'username' => $actor['username'] ?? null,
            'action' => 'module_config_updated',
            'event_type' => 'admin',
            'details' => [
                'name' => $name,
                'keys_changed' => $keysChanged,
            ],
            'ip_address' => $actor['ip_address'] ?? null,
            'target_type' => 'module',
            'target_id' => $name,
        ]);

        return ModuleConfigurationResult::ok();
    }

    /**
     * Read the currently-persisted value for each property declared in
     * the module's schema. Missing settings are simply absent — the
     * frontend treats absence as "use schema default / leave field
     * empty". Stored values come back through `json_decode` so
     * booleans, integers and strings survive the round-trip.
     *
     * Returns an empty array when the module has no config schema
     * (the controller's 400 path will have already fired in that
     * case, but the no-schema shape stays sane for other callers).
     *
     * @return array<string, mixed>
     */
    public function readPersistedValues(string $name): array
    {
        $schema = ModuleRegistry::configSchema($name);
        if ($schema === null) {
            return [];
        }

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
