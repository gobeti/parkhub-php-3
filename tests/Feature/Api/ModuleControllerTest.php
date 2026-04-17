<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the enriched `/api/v1/modules` envelope and the per-module
 * show endpoint. The Rust edition ships the same shape from
 * `parkhub-server`; keeping this test in lock-step with the Rust unit
 * tests in `parkhub-server/src/api/modules_meta.rs` is how we stop the
 * two backends from drifting.
 *
 * All payloads travel through the global ApiResponseWrapper envelope
 * (`{success, data, error, meta}`), so the backwards-compat fields
 * live at `data.modules` / `data.module_info`.
 */
class ModuleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_backward_compat_envelope(): void
    {
        $response = $this->getJson('/api/v1/modules');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertIsArray($data);

        $this->assertArrayHasKey('modules', $data);
        $this->assertArrayHasKey('module_info', $data);
        $this->assertArrayHasKey('version', $data);

        // `modules` is the historic {name => bool} map — keep it that way.
        $this->assertIsArray($data['modules']);
        foreach ($data['modules'] as $name => $enabled) {
            $this->assertIsString($name, 'module map keys must be strings');
            $this->assertIsBool($enabled, "module '{$name}' must map to a bool");
        }

        $this->assertArrayHasKey('bookings', $data['modules']);
        $this->assertArrayHasKey('vehicles', $data['modules']);
    }

    public function test_index_includes_module_info_array(): void
    {
        $response = $this->getJson('/api/v1/modules');
        $response->assertOk();

        $moduleInfo = $response->json('data.module_info');
        $this->assertIsArray($moduleInfo);
        $this->assertNotEmpty($moduleInfo);

        // Every entry must carry the full ModuleInfo contract that
        // parkhub-web relies on to render the Modules Dashboard.
        foreach ($moduleInfo as $entry) {
            $this->assertIsArray($entry);
            $this->assertArrayHasKey('name', $entry);
            $this->assertArrayHasKey('category', $entry);
            $this->assertArrayHasKey('description', $entry);
            $this->assertArrayHasKey('enabled', $entry);
            $this->assertArrayHasKey('runtime_toggleable', $entry);
            $this->assertArrayHasKey('runtime_enabled', $entry);
            $this->assertArrayHasKey('config_keys', $entry);
            $this->assertArrayHasKey('ui_route', $entry);
            $this->assertArrayHasKey('depends_on', $entry);
            $this->assertArrayHasKey('version', $entry);
            // v3: every ModuleInfo must carry a `config_schema` slot
            // (null for env-only modules, JSON Schema object otherwise).
            $this->assertArrayHasKey('config_schema', $entry);
            $this->assertTrue(
                $entry['config_schema'] === null || is_array($entry['config_schema']),
                "config_schema of '{$entry['name']}' must be array|null",
            );

            $this->assertIsString($entry['name']);
            $this->assertIsString($entry['category']);
            $this->assertIsString($entry['description']);
            $this->assertIsBool($entry['enabled']);
            $this->assertIsBool($entry['runtime_toggleable']);
            $this->assertIsBool($entry['runtime_enabled']);
            $this->assertIsArray($entry['config_keys']);
            $this->assertIsArray($entry['depends_on']);
            $this->assertIsString($entry['version']);
            // ui_route is nullable string; both are acceptable.
            $this->assertTrue(
                $entry['ui_route'] === null || is_string($entry['ui_route']),
                "ui_route of '{$entry['name']}' must be string|null",
            );
            if (is_string($entry['ui_route'])) {
                $this->assertStringStartsWith('/', $entry['ui_route']);
            }
        }
    }

    public function test_show_returns_known_module(): void
    {
        $response = $this->getJson('/api/v1/modules/bookings');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'bookings')
            ->assertJsonPath('data.category', 'Core')
            ->assertJsonPath('data.ui_route', '/bookings')
            // `bookings` stays env-only (data-loss risk) so it must
            // never appear on the runtime-toggleable allowlist.
            ->assertJsonPath('data.runtime_toggleable', false);

        $this->assertIsBool($response->json('data.enabled'));
    }

    public function test_show_returns_404_for_unknown_module(): void
    {
        $response = $this->getJson('/api/v1/modules/this-module-does-not-exist');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'MODULE_NOT_FOUND');
    }

    public function test_all_modules_belong_to_a_category(): void
    {
        $valid = ModuleRegistry::CATEGORIES;
        $this->assertNotEmpty($valid);

        foreach (ModuleRegistry::all() as $entry) {
            $this->assertContains(
                $entry['category'],
                $valid,
                "module '{$entry['name']}' has unknown category '{$entry['category']}'",
            );
        }
    }

    public function test_module_names_are_unique(): void
    {
        $seen = [];
        foreach (ModuleRegistry::all() as $entry) {
            $this->assertArrayNotHasKey(
                $entry['name'],
                $seen,
                "duplicate module name in registry: {$entry['name']}",
            );
            $seen[$entry['name']] = true;
        }

        // Sanity: the registry is supposed to mirror ~60+ modules the
        // Rust edition ships. Guard against a copy-paste accident that
        // silently truncates the table.
        $this->assertGreaterThanOrEqual(50, count($seen));
    }

    // ── v2: runtime toggle contract ─────────────────────────────────────

    public function test_patch_toggles_runtime_state_for_toggleable_module(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        // `map` is on the runtime-toggleable allowlist.
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/modules/map', ['runtime_enabled' => false]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'map')
            ->assertJsonPath('data.runtime_toggleable', true)
            ->assertJsonPath('data.runtime_enabled', false);

        // Setting row persisted — the next registry materialization
        // must honour it without hitting the env flag.
        $this->assertSame('0', Setting::get('module.map.runtime_enabled'));

        // Audit log entry written.
        $this->assertDatabaseHas('audit_log', [
            'action' => 'module_runtime_toggled',
            'target_type' => 'module',
            'target_id' => 'map',
        ]);
    }

    public function test_patch_returns_409_for_non_toggleable_module(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        // `bookings` is explicitly not on the runtime-toggleable allowlist.
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/modules/bookings', ['runtime_enabled' => false]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'MODULE_NOT_TOGGLEABLE');

        // No side effect on the settings table or audit log.
        $this->assertNull(Setting::get('module.bookings.runtime_enabled'));
        $this->assertDatabaseMissing('audit_log', [
            'action' => 'module_runtime_toggled',
            'target_id' => 'bookings',
        ]);
    }

    public function test_patch_returns_404_for_unknown_module(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/modules/does-not-exist', ['runtime_enabled' => true]);

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'MODULE_NOT_FOUND');
    }

    public function test_patch_requires_admin_role(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/modules/map', ['runtime_enabled' => false]);

        $response->assertStatus(403);

        // Regular user must not be able to mutate runtime state.
        $this->assertNull(Setting::get('module.map.runtime_enabled'));
    }

    public function test_module_gate_returns_404_when_disabled(): void
    {
        // Persist a runtime override that disables `map` even though
        // the env flag is on — the gate must honour the override.
        config(['modules.map' => true]);
        ModuleRegistry::setRuntimeEnabled('map', false);

        // ApiResponseWrapper re-shapes the raw middleware reply into
        // the standard envelope, hoisting `error` to `error.code`.
        $this->getJson('/api/v1/lots/map')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'MODULE_DISABLED');
    }

    public function test_registry_applies_setting_override(): void
    {
        // env says `map` is on…
        config(['modules.map' => true]);

        // …but the admin has flipped the runtime override off.
        ModuleRegistry::setRuntimeEnabled('map', false);

        $info = ModuleRegistry::get('map');
        $this->assertNotNull($info);
        $this->assertTrue($info['enabled'], 'env-derived `enabled` must still be true');
        $this->assertFalse($info['runtime_enabled'], 'override must win for runtime_enabled');
        $this->assertTrue($info['runtime_toggleable']);

        // Flipping the override back on resets the runtime state.
        ModuleRegistry::setRuntimeEnabled('map', true);
        $info = ModuleRegistry::get('map');
        $this->assertNotNull($info);
        $this->assertTrue($info['runtime_enabled']);

        // Non-toggleable modules must ignore setRuntimeEnabled calls so
        // a misuse in the controller layer can't bypass the allowlist.
        $before = Setting::get('module.bookings.runtime_enabled');
        ModuleRegistry::setRuntimeEnabled('bookings', false);
        $this->assertSame($before, Setting::get('module.bookings.runtime_enabled'));

        // And unused audit log entries don't leak. (Sanity.)
        $this->assertSame(0, AuditLog::where('target_id', 'bookings')->count());
    }
}
