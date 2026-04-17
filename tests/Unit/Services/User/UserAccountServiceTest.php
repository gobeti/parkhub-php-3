<?php

declare(strict_types=1);

namespace Tests\Unit\Services\User;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Favorite;
use App\Models\Notification;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\User\UserAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserAccountServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): UserAccountService
    {
        return app(UserAccountService::class);
    }

    private function createUserWithBooking(string $plate = 'M-AB 1234'): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Main Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'A1',
            'status' => 'available',
        ]);
        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => 'Main Lot',
            'slot_number' => 'A1',
            'vehicle_plate' => $plate,
            'notes' => 'please reserve',
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        return [$user, $booking];
    }

    public function test_anonymize_rejects_wrong_password_without_mutating_data(): void
    {
        [$user, $booking] = $this->createUserWithBooking('HH-CD 9999');

        $ok = $this->service()->anonymize($user, 'not-the-password', 'test', '203.0.113.1');

        $this->assertFalse($ok);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'vehicle_plate' => 'HH-CD 9999',
        ]);
    }

    public function test_anonymize_strips_pii_but_preserves_booking_row(): void
    {
        [$user, $booking] = $this->createUserWithBooking('B-XY 1234');
        Favorite::create(['user_id' => $user->id, 'slot_id' => $booking->slot_id]);
        Notification::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => 'info',
            'title' => 'Hello',
            'message' => 'Booking confirmed.',
            'read' => false,
        ]);

        $ok = $this->service()->anonymize($user, 'password', 'Tired of parking.', '203.0.113.2');

        $this->assertTrue($ok);

        $booking->refresh();
        $this->assertSame('[GELÖSCHT]', $booking->vehicle_plate);
        $this->assertNull($booking->notes);

        $this->assertSame(0, Favorite::where('user_id', $user->id)->count());
        $this->assertSame(0, Notification::where('user_id', $user->id)->count());

        $user->refresh();
        $this->assertSame('[Gelöschter Nutzer]', $user->name);
        $this->assertStringEndsWith('@deleted.invalid', $user->email);
        $this->assertFalse((bool) $user->is_active);
        $this->assertNull($user->phone);
    }

    public function test_anonymize_writes_gdpr_erasure_audit_log(): void
    {
        [$user] = $this->createUserWithBooking();

        $this->service()->anonymize($user, 'password', 'On request.', '203.0.113.3');

        $audit = AuditLog::query()->where('action', 'gdpr_erasure')->first();
        $this->assertNotNull($audit);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame('On request.', $audit->details['reason']);
        $this->assertSame('203.0.113.3', $audit->ip_address);
    }

    public function test_anonymize_scrubs_guest_bookings_created_by_user(): void
    {
        [$user, $booking] = $this->createUserWithBooking();

        $guestId = (string) Str::uuid();
        DB::table('guest_bookings')->insert([
            'id' => $guestId,
            'created_by' => $user->id,
            'lot_id' => $booking->lot_id,
            'slot_id' => $booking->slot_id,
            'guest_name' => 'Mr. Real Name',
            'guest_code' => Str::random(16),
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service()->anonymize($user, 'password', 'User request', '203.0.113.4');

        $row = DB::table('guest_bookings')->where('id', $guestId)->first();
        $this->assertNotNull($row);
        $this->assertSame('Anonymous', $row->guest_name);
    }

    public function test_export_data_contains_profile_bookings_and_vehicles(): void
    {
        [$user] = $this->createUserWithBooking('BE-RL 4242');
        Vehicle::create([
            'user_id' => $user->id,
            'plate' => 'BE-RL 4242',
            'make' => 'Tesla',
            'model' => 'Model 3',
            'color' => 'white',
        ]);

        $export = $this->service()->exportData($user);

        $this->assertSame($user->email, $export['profile']['email']);
        $this->assertCount(1, $export['bookings']);
        $this->assertCount(1, $export['vehicles']);
        $this->assertArrayHasKey('exported_at', $export);
    }

    public function test_export_data_excludes_other_users_rows(): void
    {
        [$me] = $this->createUserWithBooking('ME-00 0001');
        $other = User::factory()->create();
        Vehicle::create([
            'user_id' => $other->id,
            'plate' => 'OT-HR 0002',
            'make' => 'VW',
        ]);

        $export = $this->service()->exportData($me);

        $this->assertCount(0, $export['vehicles']);
    }

    public function test_build_ical_feed_renders_active_bookings_with_crlf_line_endings(): void
    {
        [$user, $booking] = $this->createUserWithBooking();
        Setting::set('company_name', 'TestCo');

        $feed = $this->service()->buildIcalFeed($user);

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $feed);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $feed);
        $this->assertStringContainsString('X-WR-CALNAME:TestCo Parking', $feed);
        $this->assertStringContainsString('UID:'.$booking->id.'@parkhub', $feed);
        $this->assertStringContainsString('SUMMARY:Parking: A1 (Main Lot)', $feed);
    }

    public function test_build_ical_feed_skips_cancelled_bookings(): void
    {
        [$user] = $this->createUserWithBooking();
        Booking::where('user_id', $user->id)->update(['status' => 'cancelled']);

        $feed = $this->service()->buildIcalFeed($user);

        $this->assertStringNotContainsString('BEGIN:VEVENT', $feed);
    }
}
