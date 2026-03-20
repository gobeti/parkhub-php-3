<?php

namespace App\Jobs;

use App\Mail\PasswordResetEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPasswordResetNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        private string $email,
        private string $recipientName,
        private string $resetToken,
        private string $appUrl,
    ) {}

    public function handle(): void
    {
        Mail::to($this->email)->send(
            new PasswordResetEmail($this->recipientName, $this->resetToken, $this->appUrl)
        );

        Log::info("SendPasswordResetNotificationJob: sent reset email to {$this->email}");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("SendPasswordResetNotificationJob: permanently failed", [
            'email' => $this->email,
            'error' => $e->getMessage(),
        ]);
    }
}
