<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicPricingTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    private function createLotWithSlots(int $totalSlots = 10, ?array $pricingRules = null): ParkingLot
    {
        $lot = ParkingLot::create([
            'name' => 'Dynamic Lot',
            'total_slots' => $totalSlots,
            'hourly_rate' => 5.00,
            'currency' => 'EUR',
            'status' => 'open',
            'dynamic_pricing_rules' => $pricingRules,
        ]);

        for ($i = 1; $i <= $totalSlots; $i++) {
            ParkingSlot::create([
                'lot_id' => $lot->id,
                'slot_number' => str_pad($i, 3, '0', STR_PAD_LEFT),
                'status' => 'available',
            ]);
        }

        return $lot;
    }

    private function occupySlots(ParkingLot $lot, int $count): void
    {
        $user = User::factory()->create();
        $slots = $lot->slots()->limit($count)->get();
        foreach ($slots as $slot) {
            Booking::create([
                'user_id' => $user->id,
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'status' => 'confirmed',
                'start_time' => now()->subHour(),
                'end_time' => now()->addHour(),
            ]);
        }
    }

    // ── GET /api/v1/lots/{id}/pricing/dynamic ─────────────────────────────

    public function test_get_dynamic_price_returns_normal_when_disabled(): void
    {
        $user = User::factory()->create();
        $lot = $this->createLotWithSlots(10);

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson("/api/v1/lots/{$lot->id}/pricing/dynamic");

        $response->assertOk()
            ->assertJsonPath('data.dynamic_pricing_active', false)
            ->assertJsonPath('data.tier', 'normal');
        $this->assertEquals(1.0, $response->json('data.applied_multiplier'));
    }

    public function test_get_dynamic_price_surge_when_high_occupancy(): void
    {
        $user = User::factory()->create();
        $lot = $this->createLotWithSlots(10, [
            'enabled' => true,
            'base_price' => 5.00,
            'surge_multiplier' => 1.5,
            'discount_multiplier' => 0.8,
            'surge_threshold' => 80,
            'discount_threshold' => 20,
        ]);

        // Occupy 9/10 slots = 90% > 80% threshold
        $this->occupySlots($lot, 9);

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson("/api/v1/lots/{$lot->id}/pricing/dynamic");

        $response->assertOk()
            ->assertJsonPath('data.dynamic_pricing_active', true)
            ->assertJsonPath('data.tier', 'surge')
            ->assertJsonPath('data.applied_multiplier', 1.5)
            ->assertJsonPath('data.current_price', 7.50)
            ->assertJsonPath('data.currency', 'EUR');
    }

    public function test_get_dynamic_price_discount_when_low_occupancy(): void
    {
        $user = User::factory()->create();
        $lot = $this->createLotWithSlots(10, [
            'enabled' => true,
            'base_price' => 5.00,
            'surge_multiplier' => 1.5,
            'discount_multiplier' => 0.8,
            'surge_threshold' => 80,
            'discount_threshold' => 20,
        ]);

        // Occupy 1/10 = 10% < 20% threshold
        $this->occupySlots($lot, 1);

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson("/api/v1/lots/{$lot->id}/pricing/dynamic");

        $response->assertOk()
            ->assertJsonPath('data.tier', 'discount')
            ->assertJsonPath('data.applied_multiplier', 0.8);
        $this->assertEquals(4.00, $response->json('data.current_price'));
    }

    public function test_dynamic_price_requires_auth(): void
    {
        $lot = $this->createLotWithSlots();

        $this->getJson("/api/v1/lots/{$lot->id}/pricing/dynamic")
            ->assertStatus(401);
    }

    // ── Admin: GET /api/v1/admin/lots/{id}/pricing/dynamic ────────────────

    public function test_admin_get_pricing_rules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $rules = [
            'enabled' => true,
            'base_price' => 3.00,
            'surge_multiplier' => 2.0,
            'discount_multiplier' => 0.5,
            'surge_threshold' => 75,
            'discount_threshold' => 15,
        ];
        $lot = $this->createLotWithSlots(10, $rules);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson("/api/v1/admin/lots/{$lot->id}/pricing/dynamic");

        $response->assertOk()
            ->assertJsonPath('data.enabled', true);
        $this->assertEquals(3.00, $response->json('data.base_price'));
        $this->assertEquals(2.0, $response->json('data.surge_multiplier'));
    }

    public function test_admin_get_returns_defaults_when_no_rules_set(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lot = $this->createLotWithSlots(10);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson("/api/v1/admin/lots/{$lot->id}/pricing/dynamic");

        $response->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.surge_multiplier', 1.5)
            ->assertJsonPath('data.discount_multiplier', 0.8);
    }

    // ── Admin: PUT /api/v1/admin/lots/{id}/pricing/dynamic ────────────────

    public function test_admin_update_pricing_rules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lot = $this->createLotWithSlots(10);

        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/v1/admin/lots/{$lot->id}/pricing/dynamic", [
                'enabled' => true,
                'base_price' => 4.00,
                'surge_multiplier' => 2.0,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.enabled', true);
        $this->assertEquals(4.00, $response->json('data.base_price'));
        $this->assertEquals(2.0, $response->json('data.surge_multiplier'));
        $this->assertEquals(0.8, $response->json('data.discount_multiplier')); // default preserved

        // Verify persisted
        $lot->refresh();
        $this->assertTrue($lot->dynamic_pricing_rules['enabled']);
        $this->assertEquals(4.00, $lot->dynamic_pricing_rules['base_price']);
    }

    public function test_admin_update_validates_surge_multiplier_min(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lot = $this->createLotWithSlots(10);

        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/v1/admin/lots/{$lot->id}/pricing/dynamic", [
                'surge_multiplier' => 0.5, // below min of 1
            ]);

        $response->assertStatus(422);
    }

    public function test_non_admin_cannot_update_pricing_rules(): void
    {
        $user = User::factory()->create();
        $lot = $this->createLotWithSlots(10);

        $this->withHeaders($this->authHeader($user))
            ->putJson("/api/v1/admin/lots/{$lot->id}/pricing/dynamic", [
                'enabled' => true,
            ])
            ->assertStatus(403);
    }

    // ── Module disabled ───────────────────────────────────────────────────

    public function test_disabled_module_returns_404(): void
    {
        config(['modules.dynamic_pricing' => false]);

        $user = User::factory()->create();
        $lot = $this->createLotWithSlots(10);

        $this->withHeaders($this->authHeader($user))
            ->getJson("/api/v1/lots/{$lot->id}/pricing/dynamic")
            ->assertNotFound();
    }
}
