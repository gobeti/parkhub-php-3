<?php

namespace Tests\Feature;

use App\Models\ChargingSession;
use App\Models\EvCharger;
use App\Models\ParkingLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EVChargingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createLotWithCharger(string $status = 'available'): array
    {
        $lot = ParkingLot::create([
            'name' => 'EV Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);

        $charger = EvCharger::create([
            'lot_id' => $lot->id,
            'label' => 'Charger A1',
            'connector_type' => 'ccs',
            'power_kw' => 50,
            'status' => $status,
        ]);

        return [$lot, $charger];
    }

    public function test_unauthenticated_cannot_list_chargers(): void
    {
        $this->getJson('/api/v1/lots/fake-id/chargers')->assertStatus(401);
    }

    public function test_list_chargers_for_lot(): void
    {
        $user = User::factory()->create();
        [$lot, $charger] = $this->createLotWithCharger();

        $response = $this->actingAs($user)->getJson("/api/v1/lots/{$lot->id}/chargers");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Charger A1')
            ->assertJsonPath('data.0.connector_type', 'ccs');
    }

    public function test_start_charging_session(): void
    {
        $user = User::factory()->create();
        [$lot, $charger] = $this->createLotWithCharger();

        $response = $this->actingAs($user)->postJson("/api/v1/chargers/{$charger->id}/start");

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active');

        $this->assertEquals('in_use', $charger->fresh()->status);
    }

    public function test_cannot_start_on_unavailable_charger(): void
    {
        $user = User::factory()->create();
        [$lot, $charger] = $this->createLotWithCharger('in_use');

        $response = $this->actingAs($user)->postJson("/api/v1/chargers/{$charger->id}/start");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'CHARGER_UNAVAILABLE');
    }

    public function test_cannot_start_with_active_session(): void
    {
        $user = User::factory()->create();
        [$lot, $charger1] = $this->createLotWithCharger();

        $charger2 = EvCharger::create([
            'lot_id' => $lot->id,
            'label' => 'Charger B1',
            'connector_type' => 'type2',
            'power_kw' => 22,
            'status' => 'available',
        ]);

        // Start first session
        $this->actingAs($user)->postJson("/api/v1/chargers/{$charger1->id}/start")->assertStatus(201);

        // Try to start second
        $response = $this->actingAs($user)->postJson("/api/v1/chargers/{$charger2->id}/start");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'SESSION_ACTIVE');
    }

    public function test_stop_charging_session(): void
    {
        $user = User::factory()->create();
        [$lot, $charger] = $this->createLotWithCharger();

        // Start
        $this->actingAs($user)->postJson("/api/v1/chargers/{$charger->id}/start")->assertStatus(201);

        // Stop
        $response = $this->actingAs($user)->postJson("/api/v1/chargers/{$charger->id}/stop");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'completed');

        $this->assertEquals('available', $charger->fresh()->status);
    }

    public function test_stop_without_active_session_fails(): void
    {
        $user = User::factory()->create();
        [$lot, $charger] = $this->createLotWithCharger();

        $response = $this->actingAs($user)->postJson("/api/v1/chargers/{$charger->id}/stop");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'NO_ACTIVE_SESSION');
    }

    public function test_list_user_sessions(): void
    {
        $user = User::factory()->create();
        [$lot, $charger] = $this->createLotWithCharger();

        ChargingSession::create([
            'charger_id' => $charger->id,
            'user_id' => $user->id,
            'start_time' => now()->subHour(),
            'end_time' => now(),
            'kwh_consumed' => 12.5,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/chargers/sessions');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_charger_stats(): void
    {
        $admin = User::factory()->admin()->create();
        [$lot, $charger] = $this->createLotWithCharger();

        ChargingSession::create([
            'charger_id' => $charger->id,
            'user_id' => $admin->id,
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
            'kwh_consumed' => 25.0,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/chargers');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_chargers', 1)
            ->assertJsonPath('data.available', 1)
            ->assertJsonPath('data.total_sessions', 1)
            ->assertJsonPath('data.total_kwh', 25);
    }

    public function test_admin_create_charger(): void
    {
        $admin = User::factory()->admin()->create();
        $lot = ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/chargers', [
            'lot_id' => $lot->id,
            'label' => 'New Charger',
            'connector_type' => 'type2',
            'power_kw' => 22,
            'location_hint' => 'Near exit B',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.label', 'New Charger')
            ->assertJsonPath('data.connector_type', 'type2');
    }

    public function test_disabled_ev_charging_module_returns_404(): void
    {
        config(['modules.ev_charging' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/lots/fake-id/chargers')->assertNotFound();
    }
}
