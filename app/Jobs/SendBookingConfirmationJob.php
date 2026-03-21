<?php

namespace App\Jobs;

use App\Mail\BookingConfirmation;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBookingConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        private string $bookingId,
        private string $userId,
    ) {}

    public function handle(): void
    {
        $booking = Booking::find($this->bookingId);
        $user = User::find($this->userId);

        if (! $booking || ! $user || ! $user->email) {
            Log::info('SendBookingConfirmationJob: skipped — missing booking/user/email', [
                'booking_id' => $this->bookingId,
                'user_id' => $this->userId,
            ]);

            return;
        }

        Mail::to($user->email)->send(new BookingConfirmation($booking, $user));

        Log::info("SendBookingConfirmationJob: sent confirmation for booking {$this->bookingId}");
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendBookingConfirmationJob: permanently failed', [
            'booking_id' => $this->bookingId,
            'user_id' => $this->userId,
            'error' => $e->getMessage(),
        ]);
    }
}
