<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_wizard_status_returns_initial_state(): void
    {
        $response = $this->getJson('/api/v1/setup/wizard/status');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.completed', false)
            ->assertJsonCount(4, 'data.steps');
    }

    public function test_wizard_status_is_public_no_auth(): void
    {
        // No auth header — should still work
        $response = $this->getJson('/api/v1/setup/wizard/status');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_wizard_step1_saves_company_info(): void
    {
        $response = $this->postJson('/api/v1/setup/wizard', [
            'step' => 1,
            'company_name' => 'ParkCorp GmbH',
            'timezone' => 'Europe/Berlin',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.step', 1);

        $this->assertEquals('ParkCorp GmbH', Setting::get('company_name'));
        $this->assertEquals('Europe/Berlin', Setting::get('timezone'));
    }

    public function test_wizard_step1_requires_company_name(): void
    {
        $response = $this->postJson('/api/v1/setup/wizard', [
            'step' => 1,
        ]);

        $response->assertStatus(422);
    }

    public function test_wizard_step2_creates_lot_with_floors(): void
    {
        $response = $this->postJson('/api/v1/setup/wizard', [
            'step' => 2,
            'lot_name' => 'Main Garage',
            'floor_count' => 3,
            'slots_per_floor' => 10,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.step', 2);

        $this->assertDatabaseHas('parking_lots', ['name' => 'Main Garage']);
        $lot = ParkingLot::where('name', 'Main Garage')->first();
        $this->assertEquals(30, $lot->total_slots);
        $this->assertDatabaseCount('zones', 3);
        $this->assertDatabaseCount('parking_slots', 30);
    }

    public function test_wizard_step3_invites_users(): void
    {
        // Create admin first
        User::factory()->create(['role' => 'admin']);

        $response = $this->postJson('/api/v1/setup/wizard', [
            'step' => 3,
            'invite_emails' => ['alice@company.com', 'bob@company.com'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.step', 3);

        $this->assertDatabaseHas('users', ['email' => 'alice@company.com']);
        $this->assertDatabaseHas('users', ['email' => 'bob@company.com']);
    }

    public function test_wizard_step4_saves_theme_and_completes(): void
    {
        $response = $this->postJson('/api/v1/setup/wizard', [
            'step' => 4,
            'theme' => 'neon',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.step', 4)
            ->assertJsonPath('data.theme', 'neon');

        $this->assertEquals('neon', Setting::get('wizard_theme'));
        $this->assertEquals('true', Setting::get('wizard_completed'));
    }

    public function test_wizard_step4_rejects_invalid_theme(): void
    {
        $response = $this->postJson('/api/v1/setup/wizard', [
            'step' => 4,
            'theme' => 'nonexistent-theme',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INVALID_THEME');
    }

    public function test_wizard_status_reflects_completed_steps(): void
    {
        Setting::set('company_name', 'TestCorp');
        ParkingLot::create(['name' => 'Lot 1', 'total_slots' => 5, 'available_slots' => 5, 'status' => 'open']);
        Setting::set('wizard_theme', 'classic');
        Setting::set('wizard_completed', 'true');

        $response = $this->getJson('/api/v1/setup/wizard/status');

        $response->assertStatus(200)
            ->assertJsonPath('data.completed', true);

        $steps = $response->json('data.steps');
        $this->assertTrue($steps[0]['completed']); // company
        $this->assertTrue($steps[1]['completed']); // lot
        $this->assertTrue($steps[3]['completed']); // theme
    }
}
