<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ICalTest extends TestCase
{
    use RefreshDatabase;

    private function enableModule(): void
    {
        config(['modules.ical' => true]);
    }

    public function test_authenticated_ical_feed_returns_calendar(): void
    {
        $this->enableModule();
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => 10,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'A1',
            'status' => 'available',
        ]);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $lot->name,
            'slot_number' => $slot->slot_number,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(4),
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($user)->get('/api/v1/calendar/ical');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
        $this->assertStringContainsString('BEGIN:VEVENT', $response->getContent());
        $this->assertStringContainsString('Parking: Test Lot', $response->getContent());
    }

    public function test_ical_feed_requires_auth(): void
    {
        $this->enableModule();

        $this->getJson('/api/v1/calendar/ical')->assertUnauthorized();
    }

    public function test_generate_token_creates_ical_token(): void
    {
        $this->enableModule();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/calendar/token');

        $response->assertOk();
        $response->assertJsonStructure(['success', 'data' => ['token', 'url']]);
        $this->assertTrue($response->json('success'));

        $user->refresh();
        $this->assertNotNull($user->ical_token);
        $this->assertEquals(48, strlen($user->ical_token));
    }

    public function test_public_feed_with_valid_token(): void
    {
        $this->enableModule();
        $user = User::factory()->create(['ical_token' => 'testtoken123456789012345678901234567890123456']);

        $response = $this->get('/api/v1/calendar/ical/testtoken123456789012345678901234567890123456');

        $response->assertStatus(200);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
    }

    public function test_public_feed_with_invalid_token_returns_404(): void
    {
        $this->enableModule();

        $response = $this->get('/api/v1/calendar/ical/invalidtoken');
        $response->assertStatus(404);
    }

    public function test_ical_feed_excludes_old_bookings(): void
    {
        $this->enableModule();
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Old Lot',
            'total_slots' => 10,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'B1',
            'status' => 'available',
        ]);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $lot->name,
            'slot_number' => $slot->slot_number,
            'start_time' => now()->subMonths(6),
            'end_time' => now()->subMonths(6)->addHours(2),
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user)->get('/api/v1/calendar/ical');
        $response->assertStatus(200);
        // Old booking (>3 months ago) should be excluded
        $this->assertStringNotContainsString('Old Lot', $response->getContent());
    }

    public function test_ical_calendar_contains_proper_headers(): void
    {
        $this->enableModule();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/api/v1/calendar/ical');

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringContainsString('VERSION:2.0', $content);
        $this->assertStringContainsString('PRODID:-//ParkHub//ParkHub Calendar//EN', $content);
        $this->assertStringContainsString('METHOD:PUBLISH', $content);
    }

    public function test_generate_token_regenerates_existing_token(): void
    {
        $this->enableModule();
        $user = User::factory()->create(['ical_token' => 'oldtoken1234567890123456789012345678901234567']);

        $response = $this->actingAs($user)->postJson('/api/v1/calendar/token');
        $response->assertOk();

        $user->refresh();
        $this->assertNotEquals('oldtoken1234567890123456789012345678901234567', $user->ical_token);
    }

    public function test_ical_module_disabled_returns_404(): void
    {
        config(['modules.ical' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/api/v1/calendar/ical')->assertNotFound();
    }
}
