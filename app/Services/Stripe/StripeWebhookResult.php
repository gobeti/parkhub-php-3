<?php

declare(strict_types=1);

namespace App\Services\Stripe;

/**
 * Outcome of StripeWebhookService::process().
 *
 * The service returns an enum-tagged value object so the controller can
 * shape the HTTP envelope without re-implementing any webhook decision.
 */
enum StripeWebhookOutcome: string
{
    case Received = 'received';
    case AlreadyProcessed = 'already_processed';
    case NotConfigured = 'not_configured';
    case InvalidSignature = 'invalid_signature';
}

final class StripeWebhookResult
{
    public function __construct(
        public readonly StripeWebhookOutcome $outcome,
        public readonly int $status,
        public readonly ?string $eventType = null,
        public readonly ?string $errorMessage = null,
    ) {}

    public static function received(?string $eventType): self
    {
        return new self(StripeWebhookOutcome::Received, 200, eventType: $eventType);
    }

    /**
     * The webhook was delivered at-least-once and we have already processed
     * this event_id — ack the retry with 200 so Stripe stops resending, but
     * do not run the domain mutation again.
     */
    public static function alreadyProcessed(?string $eventType): self
    {
        return new self(StripeWebhookOutcome::AlreadyProcessed, 200, eventType: $eventType);
    }

    public static function notConfigured(): self
    {
        return new self(
            StripeWebhookOutcome::NotConfigured,
            503,
            errorMessage: 'Webhook signature verification is not configured on this server',
        );
    }

    public static function invalidSignature(): self
    {
        return new self(
            StripeWebhookOutcome::InvalidSignature,
            403,
            errorMessage: 'Invalid signature',
        );
    }

    public function isOk(): bool
    {
        return $this->outcome === StripeWebhookOutcome::Received
            || $this->outcome === StripeWebhookOutcome::AlreadyProcessed;
    }
}
