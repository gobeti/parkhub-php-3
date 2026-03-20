<?php

namespace Tests\Feature;

use App\Jobs\AggregateOccupancyStatsJob;
use App\Jobs\NotifyLotClosureJob;
use App\Jobs\PurgeExpiredBookingsJob;
use App\Jobs\SendBookingConfirmationJob;
use App\Jobs\SendBookingReminderJob;
use App\Jobs\SendPasswordResetNotificationJob;
use App\Mail\BookingConfirmation;
use App\Mail\BookingReminderMail;
use App\Mail\PasswordResetEmail;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class JobQueueTest extends TestCase
{
    use RefreshDatabase;

    private function createUserLotSlot(): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Queue Test Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'Q1',
            'status' => 'available',
        ]);

        return [$user, $lot, $slot];
    }

    private function createBooking(User $user, ParkingLot $lot, ParkingSlot $slot, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $lot->name,
            'slot_number' => $slot->slot_number,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'status' => Booking::STATUS_CONFIRMED,
            'booking_type' => 'einmalig',
        ], $overrides));
    }

    // ── SendBookingConfirmationJob ──────────────────────────────────

    public function test_booking_confirmation_job_sends_email(): void
    {
        Mail::fake();
        [$user, $lot, $slot] = $this->createUserLotSlot();
        $booking = $this->createBooking($user, $lot, $slot);

        $job = new SendBookingConfirmationJob($booking->id, $user->id);
        $job->handle();

        // BookingConfirmation implements ShouldQueue, so Mail::fake() captures it as queued
        Mail::assertQueued(BookingConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_booking_confirmation_job_skips_missing_booking(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $job = new SendBookingConfirmationJob('nonexistent-id', $user->id);
        $job->handle();

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_booking_confirmation_job_skips_missing_user(): void
    {
        Mail::fake();
        [$user, $lot, $slot] = $this->createUserLotSlot();
        $booking = $this->createBooking($user, $lot, $slot);

        $job = new SendBookingConfirmationJob($booking->id, 'nonexistent-user-id');
        $job->handle();

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_booking_confirmation_dispatched_on_booking_create(): void
    {
        Queue::fake();
        [$user, $lot, $slot] = $this->createUserLotSlot();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addHour()->toISOString(),
                'end_time' => now()->addHours(3)->toISOString(),
            ]);

        Queue::assertPushed(SendBookingConfirmationJob::class);
    }

    // ── SendBookingReminderJob ──────────────────────────────────────

    public function test_booking_reminder_sends_for_upcoming_bookings(): void
    {
        Mail::fake();
        [$user, $lot, $slot] = $this->createUserLotSlot();

        // Booking starting 30 minutes from now (within 60-minute window)
        $this->createBooking($user, $lot, $slot, [
            'start_time' => now()->addMinutes(30),
            'end_time' => now()->addHours(2),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $job = new SendBookingReminderJob(60);
        $job->handle();

        Mail::assertSent(BookingReminderMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_booking_reminder_skips_already_checked_in(): void
    {
        Mail::fake();
        [$user, $lot, $slot] = $this->createUserLotSlot();

        $this->createBooking($user, $lot, $slot, [
            'start_time' => now()->addMinutes(30),
            'end_time' => now()->addHours(2),
            'status' => Booking::STATUS_CONFIRMED,
            'checked_in_at' => now(),
        ]);

        $job = new SendBookingReminderJob(60);
        $job->handle();

        Mail::assertNothingSent();
    }

    public function test_booking_reminder_skips_far_future_bookings(): void
    {
        Mail::fake();
        [$user, $lot, $slot] = $this->createUserLotSlot();

        // Booking starting 3 hours from now (outside 60-minute window)
        $this->createBooking($user, $lot, $slot, [
            'start_time' => now()->addHours(3),
            'end_time' => now()->addHours(5),
        ]);

        $job = new SendBookingReminderJob(60);
        $job->handle();

        Mail::assertNothingSent();
    }

    // ── SendPasswordResetNotificationJob ────────────────────────────

    public function test_password_reset_job_sends_email(): void
    {
        Mail::fake();

        $job = new SendPasswordResetNotificationJob(
            'test@example.com',
            'Test User',
            'reset-token-123',
            'http://localhost',
        );
        $job->handle();

        // PasswordResetEmail implements ShouldQueue
        Mail::assertQueued(PasswordResetEmail::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });
    }

    public function test_password_reset_dispatched_on_forgot_password(): void
    {
        Queue::fake();
        User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset@example.com',
        ]);

        Queue::assertPushed(SendPasswordResetNotificationJob::class);
    }

    // ── PurgeExpiredBookingsJob ──────────────────────────────────────

    public function test_purge_deletes_old_cancelled_bookings(): void
    {
        [$user, $lot, $slot] = $this->createUserLotSlot();

        // Old cancelled booking (120 days ago)
        $old = $this->createBooking($user, $lot, $slot, [
            'start_time' => now()->subDays(120),
            'end_time' => now()->subDays(120)->addHours(2),
            'status' => Booking::STATUS_CANCELLED,
        ]);

        // Recent cancelled booking (10 days ago)
        $recent = $this->createBooking($user, $lot, $slot, [
            'start_time' => now()->subDays(10),
            'end_time' => now()->subDays(10)->addHours(2),
            'status' => Booking::STATUS_CANCELLED,
        ]);

        $job = new PurgeExpiredBookingsJob(90);
        $job->handle();

        $this->assertDatabaseMissing('bookings', ['id' => $old->id]);
        $this->assertDatabaseHas('bookings', ['id' => $recent->id]);
    }

    public function test_purge_preserves_active_bookings(): void
    {
        [$user, $lot, $slot] = $this->createUserLotSlot();

        $active = $this->createBooking($user, $lot, $slot, [
            'start_time' => now()->subDays(120),
            'end_time' => now()->subDays(120)->addHours(2),
            'status' => Booking::STATUS_ACTIVE,
        ]);

        $job = new PurgeExpiredBookingsJob(90);
        $job->handle();

        $this->assertDatabaseHas('bookings', ['id' => $active->id]);
    }

    public function test_purge_deletes_old_completed_bookings(): void
    {
        [$user, $lot, $slot] = $this->createUserLotSlot();

        $completed = $this->createBooking($user, $lot, $slot, [
            'start_time' => now()->subDays(100),
            'end_time' => now()->subDays(100)->addHours(2),
            'status' => Booking::STATUS_COMPLETED,
        ]);

        $job = new PurgeExpiredBookingsJob(90);
        $job->handle();

        $this->assertDatabaseMissing('bookings', ['id' => $completed->id]);
    }

    public function test_purge_with_custom_retention_days(): void
    {
        [$user, $lot, $slot] = $this->createUserLotSlot();

        $booking = $this->createBooking($user, $lot, $slot, [
            'start_time' => now()->subDays(40),
            'end_time' => now()->subDays(40)->addHours(2),
            'status' => Booking::STATUS_CANCELLED,
        ]);

        // 30-day retention — should delete
        $job = new PurgeExpiredBookingsJob(30);
        $job->handle();

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    // ── AggregateOccupancyStatsJob ──────────────────────────────────

    public function test_aggregate_stats_computes_and_caches(): void
    {
        [$user, $lot, $slot] = $this->createUserLotSlot();
        $date = now()->subDay()->toDateString();

        $this->createBooking($user, $lot, $slot, [
            'start_time' => $date . ' 09:00:00',
            'end_time' => $date . ' 17:00:00',
            'status' => Booking::STATUS_COMPLETED,
        ]);

        $job = new AggregateOccupancyStatsJob($date);
        $job->handle();

        $stats = Cache::get("occupancy_stats:{$date}");
        $this->assertNotNull($stats);
        $this->assertEquals($date, $stats['date']);
        $this->assertEquals(1, $stats['total_bookings']);
        $this->assertEquals(1, $stats['unique_users']);
        $this->assertArrayHasKey('peak_occupancy', $stats);
        $this->assertArrayHasKey('hourly_occupancy', $stats);
        $this->assertArrayHasKey('lot_stats', $stats);
        $this->assertArrayHasKey('computed_at', $stats);
    }

    public function test_aggregate_stats_defaults_to_yesterday(): void
    {
        $job = new AggregateOccupancyStatsJob();
        $job->handle();

        $yesterday = now()->subDay()->toDateString();
        $stats = Cache::get("occupancy_stats:{$yesterday}");
        $this->assertNotNull($stats);
        $this->assertEquals($yesterday, $stats['date']);
    }

    public function test_aggregate_stats_handles_empty_data(): void
    {
        $date = now()->subDay()->toDateString();

        $job = new AggregateOccupancyStatsJob($date);
        $job->handle();

        $stats = Cache::get("occupancy_stats:{$date}");
        $this->assertNotNull($stats);
        $this->assertEquals(0, $stats['total_bookings']);
        $this->assertEquals(0, $stats['unique_users']);
        $this->assertEquals(0, $stats['peak_occupancy']);
    }

    public function test_aggregate_stats_per_lot_breakdown(): void
    {
        [$user, $lot, $slot] = $this->createUserLotSlot();
        $lot2 = ParkingLot::create([
            'name' => 'Second Lot',
            'total_slots' => 3,
            'available_slots' => 3,
            'status' => 'open',
        ]);

        $date = now()->subDay()->toDateString();

        $this->createBooking($user, $lot, $slot, [
            'start_time' => $date . ' 10:00:00',
            'end_time' => $date . ' 12:00:00',
            'status' => Booking::STATUS_COMPLETED,
        ]);

        $job = new AggregateOccupancyStatsJob($date);
        $job->handle();

        $stats = Cache::get("occupancy_stats:{$date}");
        $this->assertArrayHasKey($lot->id, $stats['lot_stats']);
        $this->assertArrayHasKey($lot2->id, $stats['lot_stats']);
        $this->assertEquals(1, $stats['lot_stats'][$lot->id]['bookings']);
        $this->assertEquals(0, $stats['lot_stats'][$lot2->id]['bookings']);
    }

    // ── NotifyLotClosureJob (batch) ─────────────────────────────────

    public function test_lot_closure_creates_notification(): void
    {
        [$user, $lot, $slot] = $this->createUserLotSlot();

        $job = new NotifyLotClosureJob($user->id, $lot->id, 'Maintenance work');
        $job->handle();

        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $user->id,
            'type' => 'lot_closure',
        ]);
    }

    public function test_lot_closure_cancels_future_bookings(): void
    {
        [$user, $lot, $slot] = $this->createUserLotSlot();

        $future = $this->createBooking($user, $lot, $slot, [
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHours(3),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $job = new NotifyLotClosureJob($user->id, $lot->id, 'Emergency closure');
        $job->handle();

        $this->assertDatabaseHas('bookings', [
            'id' => $future->id,
            'status' => Booking::STATUS_CANCELLED,
        ]);
    }

    public function test_lot_closure_preserves_other_lot_bookings(): void
    {
        [$user, $lot, $slot] = $this->createUserLotSlot();
        $otherLot = ParkingLot::create([
            'name' => 'Other Lot',
            'total_slots' => 3,
            'available_slots' => 3,
            'status' => 'open',
        ]);
        $otherSlot = ParkingSlot::create([
            'lot_id' => $otherLot->id,
            'slot_number' => 'O1',
            'status' => 'available',
        ]);

        $otherBooking = $this->createBooking($user, $otherLot, $otherSlot, [
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHours(3),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $job = new NotifyLotClosureJob($user->id, $lot->id, 'Closure');
        $job->handle();

        $this->assertDatabaseHas('bookings', [
            'id' => $otherBooking->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    public function test_lot_closure_skips_missing_user(): void
    {
        [$user, $lot, $slot] = $this->createUserLotSlot();

        $job = new NotifyLotClosureJob('nonexistent-user-id', $lot->id, 'Test');
        $job->handle();

        $this->assertDatabaseCount('notifications_custom', 0);
    }

    public function test_lot_closure_batch_dispatching(): void
    {
        Bus::fake();

        [$user1, $lot, $slot] = $this->createUserLotSlot();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $jobs = collect([$user1, $user2, $user3])->map(function ($user) use ($lot) {
            return new NotifyLotClosureJob($user->id, $lot->id, 'Planned maintenance');
        });

        Bus::batch($jobs->toArray())
            ->name('lot-closure-' . $lot->id)
            ->dispatch();

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 3;
        });
    }

    // ── Job retry configuration ─────────────────────────────────────

    public function test_confirmation_job_has_retry_config(): void
    {
        $job = new SendBookingConfirmationJob('test-id', 'user-id');
        $this->assertEquals(3, $job->tries);
        $this->assertEquals([30, 60, 120], $job->backoff);
    }

    public function test_reminder_job_has_retry_config(): void
    {
        $job = new SendBookingReminderJob();
        $this->assertEquals(3, $job->tries);
        $this->assertEquals([30, 60, 120], $job->backoff);
    }

    public function test_password_reset_job_has_retry_config(): void
    {
        $job = new SendPasswordResetNotificationJob('a@b.com', 'N', 't', 'http://x');
        $this->assertEquals(3, $job->tries);
        $this->assertEquals([30, 60, 120], $job->backoff);
    }

    public function test_purge_job_has_single_try(): void
    {
        $job = new PurgeExpiredBookingsJob();
        $this->assertEquals(1, $job->tries);
    }

    // ── Queue driver configuration ──────────────────────────────────

    public function test_queue_default_is_database_in_config(): void
    {
        // The config/queue.php defaults to 'database' via env('QUEUE_CONNECTION', 'database')
        // In test env it's overridden to 'sync', so verify the config file itself
        $configFile = file_get_contents(base_path('config/queue.php'));
        $this->assertStringContainsString("'database'", $configFile);
        $this->assertStringContainsString("env('QUEUE_CONNECTION', 'database')", $configFile);
    }

    public function test_failed_jobs_table_configured(): void
    {
        $this->assertEquals('failed_jobs', config('queue.failed.table'));
    }

    public function test_job_batches_table_configured(): void
    {
        $this->assertEquals('job_batches', config('queue.batching.table'));
    }
}
