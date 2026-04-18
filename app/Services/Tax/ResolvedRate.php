<?php

declare(strict_types=1);

namespace App\Services\Tax;

/**
 * Outcome of {@see TaxProfileRegistry::resolveRate()}.
 *
 * Mirrors the Rust-side `ResolvedRate` enum so the two backends describe
 * the same two cases: a standard numeric rate or an EU B2B reverse-charge
 * (zero-rated invoice with the Art. 194 note).
 */
final class ResolvedRate
{
    private function __construct(
        private readonly bool $isReverseCharge,
        private readonly float $rate,
    ) {}

    public static function standard(float $rate): self
    {
        return new self(false, $rate);
    }

    public static function reverseCharge(): self
    {
        return new self(true, 0.0);
    }

    /** Numeric rate as a multiplier (0.0 for reverse-charge). */
    public function asRate(): float
    {
        return $this->rate;
    }

    /** True when this resolution is an EU B2B reverse-charge. */
    public function isReverseCharge(): bool
    {
        return $this->isReverseCharge;
    }
}
