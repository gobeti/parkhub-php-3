<?php

namespace Tests\Unit\Models;

use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_has_fillable_attributes(): void
    {
        $webhook = new Webhook;
        $fillable = $webhook->getFillable();
        $this->assertContains('url', $fillable);
        $this->assertContains('events', $fillable);
        $this->assertContains('secret', $fillable);
        $this->assertContains('active', $fillable);
    }

    public function test_webhook_events_cast_to_array(): void
    {
        $webhook = Webhook::create([
            'url' => 'https://example.com/webhook',
            'events' => ['booking.created', 'booking.cancelled'],
            'active' => true,
        ]);

        $this->assertIsArray($webhook->events);
        $this->assertContains('booking.created', $webhook->events);
    }

    public function test_webhook_active_defaults(): void
    {
        $webhook = Webhook::create([
            'url' => 'https://example.com/hook',
            'events' => [],
        ]);

        $this->assertNotNull($webhook->id);
    }

    public function test_webhook_uses_uuid(): void
    {
        $webhook = Webhook::create([
            'url' => 'https://example.com/hook',
            'events' => ['booking.created'],
            'active' => true,
        ]);

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $webhook->id);
    }
}
