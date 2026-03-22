<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingPass;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingPassControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createBooking(): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Pass Test Lot',
            'total_slots' => 10,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'B2',
            'status' => 'occupied',
        ]);
        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now(),
            'end_time' => now()->addHours(8),
            'status' => 'confirmed',
        ]);

        return [$user, $lot, $slot, $booking];
    }

    public function test_generate_pass_for_booking(): void
    {
        [$user, $lot, $slot, $booking] = $this->createBooking();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/bookings/{$booking->id}/pass");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.booking_id', $booking->id)
            ->assertJsonPath('data.lot_name', 'Pass Test Lot')
            ->assertJsonPath('data.slot_number', 'B2')
            ->assertJsonPath('data.status', 'active');

        $this->assertNotNull($response->json('data.verification_code'));
        $this->assertNotNull($response->json('data.qr_data'));
    }

    public function test_generate_pass_is_idempotent(): void
    {
        [$user, $lot, $slot, $booking] = $this->createBooking();
        $token = $user->createToken('test')->plainTextToken;

        $r1 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/bookings/{$booking->id}/pass");
        $r2 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/bookings/{$booking->id}/pass");

        $this->assertEquals(
            $r1->json('data.verification_code'),
            $r2->json('data.verification_code')
        );
    }

    public function test_verify_valid_pass(): void
    {
        [$user, $lot, $slot, $booking] = $this->createBooking();
        $pass = ParkingPass::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'verification_code' => 'test-code-123',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/pass/verify/test-code-123');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.lot_name', 'Pass Test Lot');
    }

    public function test_verify_invalid_code_returns_404(): void
    {
        $response = $this->getJson('/api/v1/pass/verify/nonexistent-code');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'INVALID_PASS');
    }

    public function test_list_my_passes(): void
    {
        [$user, $lot, $slot, $booking] = $this->createBooking();
        $token = $user->createToken('test')->plainTextToken;

        ParkingPass::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'verification_code' => 'pass-abc-123',
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me/passes');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_empty_passes_returns_empty_array(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me/passes');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_unauthenticated_cannot_generate_pass(): void
    {
        $response = $this->getJson('/api/v1/bookings/some-id/pass');

        $response->assertStatus(401);
    }

    public function test_cannot_access_other_users_booking_pass(): void
    {
        [$user1, $lot, $slot, $booking] = $this->createBooking();
        $user2 = User::factory()->create(['role' => 'user']);
        $token2 = $user2->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token2)
            ->getJson("/api/v1/bookings/{$booking->id}/pass");

        $response->assertStatus(404);
    }
}
