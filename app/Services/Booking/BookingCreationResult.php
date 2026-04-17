<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Models\Booking;

/**
 * Result of BookingCreationService::create().
 *
 * Either `booking` is set (success) or `errorCode`+`errorMessage`+`status`
 * describe the failure in a way the controller can shape into the canonical
 * {success, data, error, meta} envelope without re-reading the service.
 */
final class BookingCreationResult
{
    public function __construct(
        public readonly ?Booking $booking = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly int $status = 201,
    ) {}

    public static function ok(Booking $booking): self
    {
        return new self(booking: $booking, status: 201);
    }

    public static function fail(string $code, string $message, int $status): self
    {
        return new self(errorCode: $code, errorMessage: $message, status: $status);
    }

    public function isOk(): bool
    {
        return $this->booking !== null;
    }
}
