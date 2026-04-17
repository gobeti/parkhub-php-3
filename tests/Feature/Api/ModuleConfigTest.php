<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the T-1720 v3 per-module config editor — GET/PATCH
 * `/api/v1/modules/{name}/config`. These endpoints mirror the Rust
 * edition's contract byte-for-byte: same status codes, same JSON
 * envelope, same `CONFIG_VALIDATION_FAILED` error code. Keeping the
 * assertions in lock-step with `parkhub-server/src/api/module_config.rs`
 * is how we stop the two backends from drifting while the shared
 * frontend (parkhub-web) reuses one config-editor component for both.
 */
class ModuleConfigTest extends TestCase
{
    use RefreshDatabase;

    // ── GET /api/v1/modules/{name}/config ───────────────────────────────

    public function test_get_config_returns_schema_for_themes(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/modules/themes/config');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.schema.type', 'object')
            ->assertJsonPath('data.schema.additionalProperties', false)
            ->assertJsonPath('data.schema.properties.default_theme.type', 'string')
            ->assertJsonPath('data.schema.properties.default_theme.enum', ['light', 'dark', 'classic'])
            ->assertJsonPath('data.schema.properties.allow_user_override.type', 'boolean');

        // No values have been persisted yet; `values` must still be an
        // object (even if empty) so the frontend can iterate it safely.
        $this->assertIsArray($response->json('data.values'));
    }

    public function test_get_config_404_for_unknown_module(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/modules/this-module-does-not-exist/config');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'MODULE_NOT_FOUND');
    }

    public function test_get_config_400_for_module_without_schema(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        // `bookings` is a real module but intentionally has no config
        // schema — it stays env-driven.
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/modules/bookings/config');

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'MODULE_HAS_NO_CONFIG_SCHEMA');
    }

    public function test_get_config_403_for_non_admin(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/modules/themes/config');

        $response->assertStatus(403);
    }

    // ── PATCH /api/v1/modules/{name}/config ─────────────────────────────

    public function test_patch_config_rejects_invalid_enum(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/modules/themes/config', [
                'values' => [
                    // "neon" is not one of light/dark/classic.
                    'default_theme' => 'neon',
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'CONFIG_VALIDATION_FAILED');

        $details = $response->json('error.details');
        $this->assertIsArray($details);
        $this->assertNotEmpty($details);

        // Nothing persisted on failure.
        $this->assertNull(Setting::get('module.themes.config.default_theme'));
    }

    public function test_patch_config_rejects_wrong_type(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/modules/widgets/config', [
                'values' => [
                    // Integer field given a string.
                    'max_widgets_per_dashboard' => 'twelve',
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'CONFIG_VALIDATION_FAILED');

        $this->assertNull(Setting::get('module.widgets.config.max_widgets_per_dashboard'));
    }

    public function test_patch_config_persists_values_round_trip(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $patch = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/modules/themes/config', [
                'values' => [
                    'default_theme' => 'dark',
                    'allow_user_override' => true,
                ],
            ]);

        $patch->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.values.default_theme', 'dark')
            ->assertJsonPath('data.values.allow_user_override', true);

        // Persisted as JSON-encoded scalars.
        $this->assertSame('"dark"', Setting::get('module.themes.config.default_theme'));
        $this->assertSame('true', Setting::get('module.themes.config.allow_user_override'));

        // GET reflects the fresh state.
        $get = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/modules/themes/config');

        $get->assertOk()
            ->assertJsonPath('data.values.default_theme', 'dark')
            ->assertJsonPath('data.values.allow_user_override', true);
    }

    public function test_patch_config_emits_audit_log(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/modules/widgets/config', [
                'values' => [
                    'max_widgets_per_dashboard' => 12,
                ],
            ]);

        $response->assertOk();

        $entry = AuditLog::where('action', 'module_config_updated')
            ->where('target_type', 'module')
            ->where('target_id', 'widgets')
            ->first();

        $this->assertNotNull($entry, 'audit log entry must be created on successful config update');
        $this->assertSame($admin->id, $entry->user_id);
        $this->assertSame(['max_widgets_per_dashboard'], $entry->details['keys_changed']);
        $this->assertSame('widgets', $entry->details['name']);
    }
}
