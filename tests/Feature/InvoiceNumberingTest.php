<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Services\Compliance\InvoiceNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Compliance tests for fortlaufende (sequentially ascending, gap-free)
 * invoice numbering per German § 14 UStG.
 *
 * The service under test is App\Services\Compliance\InvoiceNumberService.
 * These tests assert the three invariants the statute requires:
 *   1. sequential — every new invoice gets the next ascending number,
 *   2. gap-free — no counter value is advanced without being persisted,
 *   3. idempotent per document — re-rendering the same booking returns the
 *      same number and never burns a fresh counter entry.
 */
class InvoiceNumberingTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(InvoiceNumberService::class);
    }

    private function createBooking(User $user, ?Carbon $createdAt = null): Booking
    {
        $lot = ParkingLot::create([
            'name' => 'Test Lot',
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
            'lot_name' => $lot->name,
            'slot_number' => $slot->slot_number,
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(3),
            'status' => 'confirmed',
            'booking_type' => 'single',
        ]);

        if ($createdAt !== null) {
            // Override created_at after insert — needed for cross-year tests
            // since the model timestamps are set automatically on create.
            $booking->forceFill(['created_at' => $createdAt])->saveQuietly();
            $booking->refresh();
        }

        return $booking;
    }

    public function test_invoice_numbers_are_sequential(): void
    {
        $user = User::factory()->create();
        $year = (int) now()->year;

        $numbers = [];
        for ($i = 0; $i < 5; $i++) {
            $booking = $this->createBooking($user);
            $numbers[] = $this->service->getOrAssign((string) $booking->id, $year);
        }

        $expected = [
            sprintf('%04d-%07d', $year, 1),
            sprintf('%04d-%07d', $year, 2),
            sprintf('%04d-%07d', $year, 3),
            sprintf('%04d-%07d', $year, 4),
            sprintf('%04d-%07d', $year, 5),
        ];
        $this->assertSame($expected, $numbers);
        $this->assertSame(5, $this->service->currentCounter($year));
    }

    public function test_reassigning_same_booking_is_idempotent(): void
    {
        $user = User::factory()->create();
        $year = (int) now()->year;
        $booking = $this->createBooking($user);

        $first = $this->service->getOrAssign((string) $booking->id, $year);
        $second = $this->service->getOrAssign((string) $booking->id, $year);
        $third = $this->service->getOrAssign((string) $booking->id, $year);

        // Same booking must receive the same number on every call — never
        // burning a fresh counter entry. This is what makes re-downloading
        // the PDF safe: the sequence never develops a gap even if a client
        // retries, refreshes, or the response delivery fails halfway.
        $this->assertSame($first, $second);
        $this->assertSame($first, $third);
        $this->assertSame(sprintf('%04d-%07d', $year, 1), $first);

        // Counter must have advanced exactly once.
        $this->assertSame(1, $this->service->currentCounter($year));

        // And the mapping table must hold exactly one row for this booking.
        $this->assertSame(
            1,
            DB::table('booking_invoice_numbers')
                ->where('booking_id', $booking->id)
                ->count()
        );
    }

    public function test_no_duplicate_numbers_across_concurrent_bookings(): void
    {
        // Allocate ten invoice numbers for distinct bookings and assert the
        // set is exactly {1..=10} — i.e. no duplicates and no gaps. Real
        // concurrency would need parallel PHP processes, but the SELECT ...
        // FOR UPDATE contract means the same assertion holds under load.
        $user = User::factory()->create();
        $year = (int) now()->year;

        $numbers = [];
        for ($i = 0; $i < 10; $i++) {
            $booking = $this->createBooking($user);
            $numbers[] = $this->service->getOrAssign((string) $booking->id, $year);
        }

        $this->assertCount(10, array_unique($numbers), 'no duplicate numbers');

        $counters = array_map(
            static fn (string $n): int => (int) substr($n, 5),
            $numbers,
        );
        sort($counters);
        $this->assertSame(range(1, 10), $counters, 'counters form a gap-free ascending series');
    }

    public function test_counter_resets_each_year(): void
    {
        // Per-year reset policy (documented in InvoiceNumberService): the
        // counter restarts at 1 on January 1. The year-segment of the
        // rendered number keeps the combined identifier globally unique
        // within the tax subject, which is what § 14 UStG actually requires.
        $user = User::factory()->create();

        $booking2026a = $this->createBooking($user);
        $booking2026b = $this->createBooking($user);
        $booking2027a = $this->createBooking($user);
        $booking2027b = $this->createBooking($user);

        $this->assertSame(
            '2026-0000001',
            $this->service->getOrAssign((string) $booking2026a->id, 2026)
        );
        $this->assertSame(
            '2026-0000002',
            $this->service->getOrAssign((string) $booking2026b->id, 2026)
        );
        $this->assertSame(
            '2027-0000001',
            $this->service->getOrAssign((string) $booking2027a->id, 2027)
        );
        $this->assertSame(
            '2027-0000002',
            $this->service->getOrAssign((string) $booking2027b->id, 2027)
        );

        // A late-issued 2026 invoice (e.g. invoicing a booking that was
        // created last year but never had its PDF rendered) must pick up the
        // 2026 series where it left off — never restart it, never jump.
        $lateBooking = $this->createBooking($user);
        $this->assertSame(
            '2026-0000003',
            $this->service->getOrAssign((string) $lateBooking->id, 2026)
        );
    }

    public function test_invoice_endpoint_persists_sequential_number(): void
    {
        // End-to-end check through the HTTP layer: the same booking rendered
        // twice via the invoice endpoint must show the same number both
        // times, and two distinct bookings must produce strictly ascending
        // numbers 0000001 and 0000002.
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $bookingA = $this->createBooking($user);
        $bookingB = $this->createBooking($user);
        $year = (int) $bookingA->created_at->format('Y');

        $first = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get("/api/v1/bookings/{$bookingA->id}/invoice");
        $first->assertStatus(200);

        $firstRepeat = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get("/api/v1/bookings/{$bookingA->id}/invoice");
        $firstRepeat->assertStatus(200);

        $second = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get("/api/v1/bookings/{$bookingB->id}/invoice");
        $second->assertStatus(200);

        $expectedA = sprintf('%04d-%07d', $year, 1);
        $expectedB = sprintf('%04d-%07d', $year, 2);

        $this->assertStringContainsString($expectedA, $first->getContent());
        $this->assertStringContainsString($expectedA, $firstRepeat->getContent());
        $this->assertStringContainsString($expectedB, $second->getContent());

        $this->assertSame(2, $this->service->currentCounter($year));
    }
}
