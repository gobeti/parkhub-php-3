<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Booking;
use App\Models\Notification;
use App\Models\ParkingLot;
use App\Models\User;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyLotClosureJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        private string $userId,
        private string $lotId,
        private string $reason,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $user = User::find($this->userId);
        $lot = ParkingLot::find($this->lotId);

        if (! $user || ! $lot) {
            return;
        }

        Notification::create([
            'user_id' => $user->id,
            'type' => 'lot_closure',
            'title' => 'Parkplatz vorübergehend gesperrt',
            'message' => "Der Parkplatz \"{$lot->name}\" wurde gesperrt: {$this->reason}",
            'data' => ['lot_id' => $lot->id, 'reason' => $this->reason],
        ]);

        // Cancel affected future bookings for this user in this lot
        $cancelled = Booking::where('user_id', $user->id)
            ->where('lot_id', $lot->id)
            ->whereIn('status', [Booking::STATUS_CONFIRMED])
            ->where('start_time', '>', now())
            ->update(['status' => Booking::STATUS_CANCELLED]);

        if ($cancelled > 0) {
            Log::info("NotifyLotClosureJob: cancelled {$cancelled} bookings for user {$user->id} in lot {$lot->id}");
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('NotifyLotClosureJob: failed', [
            'user_id' => $this->userId,
            'lot_id' => $this->lotId,
            'error' => $e->getMessage(),
        ]);
    }
}
