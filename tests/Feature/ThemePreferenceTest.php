<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_get_default_theme(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/preferences/theme');

        $response->assertStatus(200)
            ->assertJsonPath('data.design_theme', 'classic');
    }

    public function test_update_theme_to_glass(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->putJson('/api/v1/preferences/theme', ['design_theme' => 'glass']);

        $response->assertStatus(200)
            ->assertJsonPath('data.design_theme', 'glass');

        // Verify persisted
        $this->assertEquals('glass', $user->fresh()->preferences['design_theme']);
    }

    public function test_update_theme_to_all_valid_ids(): void
    {
        $user = User::factory()->create();
        $validThemes = ['classic', 'glass', 'bento', 'brutalist', 'neon', 'warm'];

        foreach ($validThemes as $theme) {
            $response = $this->withHeaders($this->authHeader($user))
                ->putJson('/api/v1/preferences/theme', ['design_theme' => $theme]);

            $response->assertStatus(200)
                ->assertJsonPath('data.design_theme', $theme);
        }
    }

    public function test_reject_invalid_theme(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->putJson('/api/v1/preferences/theme', ['design_theme' => 'invalid_theme']);

        $response->assertStatus(422);
    }

    public function test_reject_missing_theme(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->putJson('/api/v1/preferences/theme', []);

        $response->assertStatus(422);
    }

    public function test_theme_requires_auth(): void
    {
        $this->getJson('/api/v1/preferences/theme')->assertStatus(401);
        $this->putJson('/api/v1/preferences/theme', ['design_theme' => 'glass'])->assertStatus(401);
    }

    public function test_get_returns_persisted_theme(): void
    {
        $user = User::factory()->create([
            'preferences' => ['design_theme' => 'neon'],
        ]);

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/preferences/theme');

        $response->assertStatus(200)
            ->assertJsonPath('data.design_theme', 'neon');
    }

    public function test_get_falls_back_to_classic_for_invalid_stored_theme(): void
    {
        $user = User::factory()->create([
            'preferences' => ['design_theme' => 'nonexistent'],
        ]);

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/preferences/theme');

        $response->assertStatus(200)
            ->assertJsonPath('data.design_theme', 'classic');
    }

    public function test_update_preserves_other_preferences(): void
    {
        $user = User::factory()->create([
            'preferences' => ['language' => 'de', 'design_theme' => 'classic'],
        ]);

        $this->withHeaders($this->authHeader($user))
            ->putJson('/api/v1/preferences/theme', ['design_theme' => 'warm']);

        $prefs = $user->fresh()->preferences;
        $this->assertEquals('warm', $prefs['design_theme']);
        $this->assertEquals('de', $prefs['language']);
    }
}
