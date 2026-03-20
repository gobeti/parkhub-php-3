<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_subscribe_to_push(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
                'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8p8REfWMk',
                'auth' => 'tBHItJI5svbpC7ZQMfIQ-Q',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
        ]);
    }

    public function test_subscribe_requires_all_fields(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Missing p256dh and auth
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
            ])
            ->assertStatus(422);

        // Missing endpoint
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/push/subscribe', [
                'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8p8REfWMk',
                'auth' => 'tBHItJI5svbpC7ZQMfIQ-Q',
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_subscribe_upserts_same_endpoint(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $endpoint = 'https://fcm.googleapis.com/fcm/send/upsert-test';

        // First subscribe
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/push/subscribe', [
                'endpoint' => $endpoint,
                'p256dh' => 'first-key',
                'auth' => 'first-auth',
            ])
            ->assertStatus(201);

        // Second subscribe with same endpoint — should upsert, not duplicate
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/push/subscribe', [
                'endpoint' => $endpoint,
                'p256dh' => 'updated-key',
                'auth' => 'updated-auth',
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'p256dh' => 'updated-key',
            'auth' => 'updated-auth',
        ]);
    }

    public function test_user_can_unsubscribe_from_push(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Create subscriptions
        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://example.com/push/1',
            'p256dh' => 'key1',
            'auth' => 'auth1',
        ]);
        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://example.com/push/2',
            'p256dh' => 'key2',
            'auth' => 'auth2',
        ]);

        $this->assertDatabaseCount('push_subscriptions', 2);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/push/unsubscribe');

        $response->assertStatus(200);
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_unsubscribe_does_not_affect_other_users(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $token1 = $user1->createToken('test')->plainTextToken;

        PushSubscription::create([
            'user_id' => $user1->id,
            'endpoint' => 'https://example.com/push/user1',
            'p256dh' => 'key1',
            'auth' => 'auth1',
        ]);
        PushSubscription::create([
            'user_id' => $user2->id,
            'endpoint' => 'https://example.com/push/user2',
            'p256dh' => 'key2',
            'auth' => 'auth2',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token1)
            ->deleteJson('/api/v1/push/unsubscribe')
            ->assertStatus(200);

        // User1's sub deleted, user2's intact
        $this->assertDatabaseMissing('push_subscriptions', ['user_id' => $user1->id]);
        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $user2->id]);
    }

    public function test_subscribe_requires_authentication(): void
    {
        $this->postJson('/api/push/subscribe', [
            'endpoint' => 'https://example.com/push',
            'p256dh' => 'key',
            'auth' => 'auth',
        ])->assertStatus(401);
    }
}
