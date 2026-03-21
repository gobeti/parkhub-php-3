<?php

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PurgeExpiredBookingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * @param  int  $retentionDays  Number of days to keep completed/cancelled bookings (default 90).
     */
    public function __construct(
        private int $retentionDays = 90,
    ) {}

    public function handle(): void
    {
        $cutoff = now()->subDays($this->retentionDays);

        $deleted = Booking::whereIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED, Booking::STATUS_NO_SHOW])
            ->where('end_time', '<', $cutoff)
            ->delete();

        Log::info("PurgeExpiredBookingsJob: deleted {$deleted} bookings older than {$this->retentionDays} days");
    }

    public function failed(\Throwable $e): void
    {
        Log::error('PurgeExpiredBookingsJob: failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
