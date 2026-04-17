<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\GuestBooking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Query-count regression tests for admin list endpoints.
 *
 * These tests catch N+1 regressions by seeding a page of rows and asserting
 * the handler runs under a sensible query budget. If a future refactor drops
 * an eager-load, the test will flag it immediately with a concrete N+1 count
 * instead of a silent perf regression in production.
 *
 * The budgets are deliberately loose to survive incidental wrapper queries
 * (session/auth/Setting lookups) but tight enough that per-row relation
 * lookups (O(rows)) push the total above them.
 *
 * Ref: T-1747.
 */
class AdminQueryCountTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{User, string}
     */
    private function createAdmin(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        return [$admin, $token];
    }

    /**
     * Seed N bookings owned by N distinct users, each in its own lot + slot.
     * Ensures any per-row `->user`, `->lot`, or `->slot` access would fire
     * one extra query per row in the absence of eager loading.
     */
    private function seedDistinctBookings(int $count, string $status = 'confirmed'): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->create(['role' => 'user']);
            $lot = ParkingLot::create([
                'name' => "QC Lot $i",
                'total_slots' => 1,
                'available_slots' => 1,
                'status' => 'open',
            ]);
            $slot = ParkingSlot::create([
                'lot_id' => $lot->id,
                'slot_number' => sprintf('QC%03d', $i),
                'status' => 'available',
            ]);
            Booking::create([
                'user_id' => $user->id,
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'lot_name' => $lot->name,
                'slot_number' => $slot->slot_number,
                'start_time' => now()->subDays($i + 1)->subHours(3),
                'end_time' => now()->subDays($i + 1)->subHour(),
                'status' => $status,
                'booking_type' => 'single',
                'total_price' => 5,
                'currency' => 'EUR',
            ]);
        }
    }

    /**
     * /api/v1/admin/bookings returns a page of Booking models with `->with('user')`.
     * A 20-row page should run in ~8 queries (auth + setting + pagination count +
     * booking SELECT + users IN-clause + a handful of middleware lookups), not 20+.
     */
    public function test_admin_bookings_index_has_no_n_plus_one(): void
    {
        [$admin, $token] = $this->createAdmin();
        $this->seedDistinctBookings(20);

        DB::enableQueryLog();
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/bookings?per_page=20');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertStatus(200);
        // Measured: 6 queries (auth + setting + booking SELECT with user IN +
        // paginator count + a couple of middleware lookups). Budget 15 leaves
        // room for incidental wrapper queries but still flags a dropped
        // ->with('user') — that regression would push this to 20+ on a page of 20.
        $this->assertLessThan(
            15,
            $count,
            "Admin bookings index should eager-load the 'user' relation. Got {$count} queries for 20 rows."
        );
    }

    /**
     * /api/v1/admin/guest-bookings returns guests with `->with(['lot', 'slot', 'creator'])`.
     * With 15 distinct lots/slots/creators, we still expect a low query count
     * because each relation is batched into a single IN-clause by eager loading.
     */
    public function test_admin_guest_bookings_index_has_no_n_plus_one(): void
    {
        [$admin, $token] = $this->createAdmin();

        // Seed 15 guest bookings with distinct lot/slot/creator ids.
        for ($i = 0; $i < 15; $i++) {
            $creator = User::factory()->create(['role' => 'user']);
            $lot = ParkingLot::create([
                'name' => "GB Lot $i",
                'total_slots' => 1,
                'available_slots' => 1,
                'status' => 'open',
            ]);
            $slot = ParkingSlot::create([
                'lot_id' => $lot->id,
                'slot_number' => sprintf('GB%03d', $i),
                'status' => 'available',
            ]);
            GuestBooking::create([
                'guest_name' => "Guest $i",
                'guest_code' => 'G-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->subDays($i + 1),
                'end_time' => now()->subDays($i + 1)->addHour(),
                'status' => 'confirmed',
                'created_by' => $creator->id,
            ]);
        }

        DB::enableQueryLog();
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/guest-bookings?per_page=15');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertStatus(200);
        // Measured: 8 queries (3 eager-loaded relations as IN-clauses + paginator
        // count + auth wrappers). Without ->with(['lot', 'slot', 'creator']) this
        // would fire 3 extra queries per row = 45+ for a page of 15. Budget 15
        // catches any dropped relation on that list.
        $this->assertLessThan(
            15,
            $count,
            "Admin guest-bookings should eager-load lot/slot/creator. Got {$count} queries for 15 rows."
        );
    }

    /**
     * /api/v1/bookings/history maps each Booking through `$b->lot?->name` /
     * `$b->slot?->slot_number` as a fallback. Without eager loading on lot/slot
     * those would fire 2*N queries per page. The fix eager-loads both.
     */
    public function test_bookings_history_has_no_n_plus_one(): void
    {
        $user = User::factory()->create();

        // Seed 10 completed bookings where lot_name/slot_number are NULL so the
        // relation fallback kicks in on every row — that's exactly the path
        // the eager load has to cover.
        for ($i = 0; $i < 10; $i++) {
            $lot = ParkingLot::create([
                'name' => "Hist Lot $i",
                'total_slots' => 1,
                'available_slots' => 1,
                'status' => 'open',
            ]);
            $slot = ParkingSlot::create([
                'lot_id' => $lot->id,
                'slot_number' => sprintf('H%03d', $i),
                'status' => 'available',
            ]);
            Booking::create([
                'user_id' => $user->id,
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'lot_name' => null,
                'slot_number' => null,
                'start_time' => now()->subDays($i + 1)->subHours(3),
                'end_time' => now()->subDays($i + 1)->subHour(),
                'status' => 'completed',
                'booking_type' => 'single',
                'total_price' => 5,
                'currency' => 'EUR',
            ]);
        }

        DB::enableQueryLog();
        $response = $this->actingAs($user)->getJson('/api/v1/bookings/history?per_page=10');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        // Measured: 4 queries after eager load (bookings + lots IN + slots IN +
        // paginator count) vs 12 queries without it. Budget 12 is deliberately
        // loose so future auth/tenant middleware can add a query or two without
        // failing, but still tight enough to catch a dropped ->with() call.
        $this->assertLessThan(
            12,
            $count,
            "/bookings/history must eager-load lot+slot for the fallback path. Got {$count} queries for 10 rows."
        );
    }
}
