<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Services\ModuleRegistry;
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
            // v1: runtime_toggleable is false for every module.
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
}
