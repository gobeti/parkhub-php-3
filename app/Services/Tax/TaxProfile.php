<?php

declare(strict_types=1);

namespace App\Services\Tax;

/**
 * Declarative tax profile for a single country.
 *
 * Mirrors the Rust-side `parkhub_server::api::tax::TaxProfile` struct so
 * the two backends apply identical rates and reverse-charge semantics.
 *
 * # Legal disclaimer
 *
 * Rates current as of 2026-04; consult a tax advisor for production use.
 * Values are the standard statutory rates published by each jurisdiction
 * at the time of writing and are exposed purely as sensible defaults.
 * Operators remain responsible for verifying the rate in force for their
 * business and keeping it current via the admin settings store.
 */
final class TaxProfile
{
    public function __construct(
        /** ISO 3166-1 alpha-2 country code, uppercase (e.g. "DE"). */
        public readonly string $country,
        /** Standard VAT rate as a fraction (e.g. 0.19 for 19%). */
        public readonly float $standardRate,
        /** Reduced VAT rate when the jurisdiction publishes one. */
        public readonly ?float $reducedRate,
        /** Whether this country participates in the EU B2B reverse-charge regime. */
        public readonly bool $reverseChargeEu,
    ) {}
}
