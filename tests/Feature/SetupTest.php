<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_status_returns_fresh_state(): void
    {
        $response = $this->getJson('/api/v1/setup/status');

        $response->assertStatus(200)
            ->assertJsonPath('data.setup_completed', false)
            ->assertJsonPath('data.has_admin', false)
            ->assertJsonPath('data.has_users', false);
    }

    public function test_setup_status_detects_existing_admin(): void
    {
        User::factory()->create(['role' => 'admin']);

        $response = $this->getJson('/api/v1/setup/status');

        $response->assertStatus(200)
            ->assertJsonPath('data.has_admin', true)
            ->assertJsonPath('data.has_users', true);
    }

    public function test_setup_init_creates_admin_and_completes(): void
    {
        $response = $this->postJson('/api/v1/setup', [
            'company_name' => 'Test Corp',
            'admin_username' => 'setupadmin',
            'admin_password' => 'SecurePass123',
            'admin_email' => 'admin@test.com',
            'admin_name' => 'Setup Admin',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['message', 'user', 'tokens' => ['access_token', 'token_type']]]);

        $this->assertDatabaseHas('users', [
            'username' => 'setupadmin',
        ]);

        $this->assertEquals('true', Setting::get('setup_completed'));
        $this->assertEquals('Test Corp', Setting::get('company_name'));
    }

    public function test_setup_init_with_sample_data(): void
    {
        $response = $this->postJson('/api/v1/setup', [
            'company_name' => 'Sample Corp',
            'admin_username' => 'sampleadmin',
            'admin_password' => 'SecurePass123',
            'admin_email' => 'sample@test.com',
            'admin_name' => 'Sample Admin',
            'create_sample_data' => true,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('parking_lots', ['name' => 'Sample Parking Lot']);
        $this->assertDatabaseCount('parking_slots', 10);
    }

    public function test_setup_init_blocked_after_completion(): void
    {
        Setting::set('setup_completed', 'true');

        $response = $this->postJson('/api/v1/setup', [
            'company_name' => 'Hacker Corp',
            'admin_username' => 'hacker',
            'admin_password' => 'HackPass123',
            'admin_email' => 'hack@test.com',
            'admin_name' => 'Hacker',
        ]);

        $response->assertStatus(400);
    }

    public function test_setup_init_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/setup', []);

        $response->assertStatus(422);
    }

    public function test_setup_status_is_public(): void
    {
        // No auth header — should still work
        $response = $this->getJson('/api/v1/setup/status');

        $response->assertStatus(200);
    }
}
