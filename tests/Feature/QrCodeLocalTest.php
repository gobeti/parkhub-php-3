<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrCodeLocalTest extends TestCase
{
    use RefreshDatabase;

    public function test_lot_qr_returns_local_svg(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'QR Test', 'total_slots' => 1, 'status' => 'open']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/lots/'.$lot->id.'/qr');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['lot_id', 'lot_name', 'qr_svg', 'data']]);

        // Verify it's base64 SVG, not an external URL
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $response->json('data.qr_svg'));
        $this->assertStringNotContainsString('qrserver.com', $response->json('data.qr_svg'));
    }

    public function test_slot_qr_returns_local_svg(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'QR Lot', 'total_slots' => 1, 'status' => 'open']);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'Q1', 'status' => 'available']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/lots/{$lot->id}/slots/{$slot->id}/qr");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['lot_id', 'slot_id', 'qr_svg']]);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $response->json('data.qr_svg'));
    }

    public function test_qr_svg_is_valid_base64(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'QR B64', 'total_slots' => 1, 'status' => 'open']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/lots/'.$lot->id.'/qr');

        $b64 = str_replace('data:image/svg+xml;base64,', '', $response->json('data.qr_svg'));
        $decoded = base64_decode($b64, true);
        $this->assertNotFalse($decoded);
        $this->assertStringContainsString('<svg', $decoded);
    }
}
