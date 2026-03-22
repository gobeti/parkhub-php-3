<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_get_default_notification_preferences(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/preferences/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('data.email_booking_confirm', true)
            ->assertJsonPath('data.email_reminder', true)
            ->assertJsonPath('data.push_enabled', true);
    }

    public function test_update_notification_preferences(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->putJson('/api/v1/preferences/notifications', [
                'email_booking_confirm' => false,
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.email_booking_confirm', false)
            ->assertJsonPath('data.quiet_hours_start', '22:00')
            ->assertJsonPath('data.quiet_hours_end', '07:00');

        // Verify persisted
        $this->assertFalse($user->fresh()->notification_preferences['email_booking_confirm']);
    }

    public function test_invalid_quiet_hours_format(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->putJson('/api/v1/preferences/notifications', [
                'quiet_hours_start' => 'invalid',
            ]);

        $response->assertStatus(422);
    }

    public function test_preferences_require_auth(): void
    {
        $this->getJson('/api/v1/preferences/notifications')->assertStatus(401);
    }
}
