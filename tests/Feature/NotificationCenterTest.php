<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    // ── List (enriched) ────────────────────────────────────────────

    public function test_list_center_notifications(): void
    {
        $user = User::factory()->create();

        Notification::create([
            'user_id' => $user->id, 'type' => 'booking_confirmed',
            'title' => 'Booking Confirmed', 'message' => 'Spot A-12 booked.',
        ]);
        Notification::create([
            'user_id' => $user->id, 'type' => 'maintenance_alert',
            'title' => 'Maintenance Alert', 'message' => 'Lot B closed tomorrow.',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications/center');

        $response->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonStructure([
                'data' => [
                    'items' => [['id', 'notification_type', 'title', 'message', 'read',
                        'action_url', 'icon', 'severity', 'type_label', 'created_at', 'date_group']],
                    'total', 'page', 'per_page', 'unread_count',
                ],
            ]);
    }

    public function test_list_returns_enriched_metadata(): void
    {
        $user = User::factory()->create();

        Notification::create([
            'user_id' => $user->id, 'type' => 'booking_confirmed',
            'title' => 'Booking Confirmed', 'message' => 'Done.',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications/center');

        $item = $response->json('data.items.0');
        $this->assertEquals('check-circle', $item['icon']);
        $this->assertEquals('success', $item['severity']);
        $this->assertEquals('Booking Confirmed', $item['type_label']);
        $this->assertEquals('today', $item['date_group']);
    }

    public function test_filter_unread_only(): void
    {
        $user = User::factory()->create();

        Notification::create([
            'user_id' => $user->id, 'type' => 'info',
            'title' => 'Read One', 'message' => 'Old.', 'read' => true,
        ]);
        Notification::create([
            'user_id' => $user->id, 'type' => 'info',
            'title' => 'Unread One', 'message' => 'New.', 'read' => false,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications/center?filter=unread');

        $response->assertOk()->assertJsonCount(1, 'data.items');
        $this->assertEquals('Unread One', $response->json('data.items.0.title'));
    }

    public function test_filter_by_notification_type(): void
    {
        $user = User::factory()->create();

        Notification::create([
            'user_id' => $user->id, 'type' => 'booking_confirmed',
            'title' => 'Confirmed', 'message' => 'Yes.',
        ]);
        Notification::create([
            'user_id' => $user->id, 'type' => 'payment_received',
            'title' => 'Payment', 'message' => 'EUR 5.',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications/center?notification_type=payment_received');

        $response->assertOk()->assertJsonCount(1, 'data.items');
        $this->assertEquals('Payment', $response->json('data.items.0.title'));
    }

    public function test_pagination(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            Notification::create([
                'user_id' => $user->id, 'type' => 'info',
                'title' => "Notif $i", 'message' => 'Test.',
            ]);
        }

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications/center?page=2&per_page=2');

        $response->assertOk()
            ->assertJsonPath('data.total', 5)
            ->assertJsonPath('data.page', 2)
            ->assertJsonPath('data.per_page', 2)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_per_page_capped_at_100(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications/center?per_page=500');

        $response->assertOk()->assertJsonPath('data.per_page', 100);
    }

    public function test_ownership_isolation(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Notification::create([
            'user_id' => $user1->id, 'type' => 'info',
            'title' => 'Private', 'message' => 'For user1 only.',
        ]);

        $response = $this->withHeaders($this->authHeaders($user2))
            ->getJson('/api/v1/notifications/center');

        $response->assertOk()->assertJsonCount(0, 'data.items');
    }

    // ── Unread count ───────────────────────────────────────────────

    public function test_unread_count(): void
    {
        $user = User::factory()->create();

        Notification::create(['user_id' => $user->id, 'type' => 'info', 'title' => 'A', 'message' => 'M', 'read' => false]);
        Notification::create(['user_id' => $user->id, 'type' => 'info', 'title' => 'B', 'message' => 'M', 'read' => false]);
        Notification::create(['user_id' => $user->id, 'type' => 'info', 'title' => 'C', 'message' => 'M', 'read' => true]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications/unread-count');

        $response->assertOk()->assertJsonPath('data.count', 2);
    }

    // ── Mark all read ──────────────────────────────────────────────

    public function test_mark_all_read(): void
    {
        $user = User::factory()->create();

        Notification::create(['user_id' => $user->id, 'type' => 'info', 'title' => 'A', 'message' => 'M', 'read' => false]);
        Notification::create(['user_id' => $user->id, 'type' => 'info', 'title' => 'B', 'message' => 'M', 'read' => false]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson('/api/v1/notifications/center/read-all');

        $response->assertOk()->assertJsonPath('data.updated', 2);

        $this->assertEquals(0, Notification::where('user_id', $user->id)->where('read', false)->count());
    }

    public function test_mark_all_read_only_affects_own(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Notification::create(['user_id' => $user1->id, 'type' => 'info', 'title' => 'A', 'message' => 'M', 'read' => false]);
        Notification::create(['user_id' => $user2->id, 'type' => 'info', 'title' => 'B', 'message' => 'M', 'read' => false]);

        $this->withHeaders($this->authHeaders($user1))
            ->putJson('/api/v1/notifications/center/read-all');

        $this->assertEquals(1, Notification::where('user_id', $user2->id)->where('read', false)->count());
    }

    // ── Delete ─────────────────────────────────────────────────────

    public function test_delete_notification(): void
    {
        $user = User::factory()->create();

        $n = Notification::create([
            'user_id' => $user->id, 'type' => 'info', 'title' => 'Gone', 'message' => 'M',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/v1/notifications/center/{$n->id}");

        $response->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertDatabaseMissing('notifications_custom', ['id' => $n->id]);
    }

    public function test_cannot_delete_other_users_notification(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $n = Notification::create([
            'user_id' => $user1->id, 'type' => 'info', 'title' => 'Mine', 'message' => 'M',
        ]);

        $response = $this->withHeaders($this->authHeaders($user2))
            ->deleteJson("/api/v1/notifications/center/{$n->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('notifications_custom', ['id' => $n->id]);
    }

    public function test_delete_nonexistent_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson('/api/v1/notifications/center/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    // ── Auth ───────────────────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/notifications/center')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
        $this->putJson('/api/v1/notifications/center/read-all')->assertUnauthorized();
        $this->deleteJson('/api/v1/notifications/center/fake-id')->assertUnauthorized();
    }

    // ── Type parsing heuristics ────────────────────────────────────

    public function test_type_parsing_from_title(): void
    {
        $user = User::factory()->create();

        Notification::create([
            'user_id' => $user->id, 'type' => 'unknown_type',
            'title' => 'Your booking has been confirmed!', 'message' => 'Done.',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications/center');

        $this->assertEquals('booking_confirmed', $response->json('data.items.0.notification_type'));
        $this->assertEquals('check-circle', $response->json('data.items.0.icon'));
    }

    public function test_unknown_type_falls_back_to_system_announcement(): void
    {
        $user = User::factory()->create();

        Notification::create([
            'user_id' => $user->id, 'type' => 'totally_unknown',
            'title' => 'Random Title', 'message' => 'No match.',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications/center');

        $this->assertEquals('system_announcement', $response->json('data.items.0.notification_type'));
        $this->assertEquals('megaphone', $response->json('data.items.0.icon'));
        $this->assertEquals('neutral', $response->json('data.items.0.severity'));
    }

    // ── Action URL extraction ──────────────────────────────────────

    public function test_action_url_with_booking_id(): void
    {
        $user = User::factory()->create();

        Notification::create([
            'user_id' => $user->id, 'type' => 'booking_confirmed',
            'title' => 'Confirmed', 'message' => 'Done.',
            'data' => ['booking_id' => 'abc-123'],
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications/center');

        $this->assertEquals('/bookings/abc-123', $response->json('data.items.0.action_url'));
    }

    public function test_action_url_waitlist_offer(): void
    {
        $user = User::factory()->create();

        Notification::create([
            'user_id' => $user->id, 'type' => 'waitlist_offer',
            'title' => 'Waitlist Offer', 'message' => 'Spot available.',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications/center');

        $this->assertEquals('/waitlist', $response->json('data.items.0.action_url'));
    }

    // ── All 8 notification types ───────────────────────────────────

    public function test_all_notification_types_have_correct_metadata(): void
    {
        $user = User::factory()->create();
        $types = [
            'booking_confirmed' => ['icon' => 'check-circle', 'severity' => 'success'],
            'booking_cancelled' => ['icon' => 'x-circle', 'severity' => 'warning'],
            'booking_reminder' => ['icon' => 'clock', 'severity' => 'info'],
            'waitlist_offer' => ['icon' => 'queue', 'severity' => 'info'],
            'maintenance_alert' => ['icon' => 'wrench', 'severity' => 'warning'],
            'system_announcement' => ['icon' => 'megaphone', 'severity' => 'neutral'],
            'payment_received' => ['icon' => 'currency-dollar', 'severity' => 'success'],
            'visitor_arrived' => ['icon' => 'user-plus', 'severity' => 'info'],
        ];

        foreach ($types as $type => $expected) {
            Notification::create([
                'user_id' => $user->id, 'type' => $type,
                'title' => ucfirst(str_replace('_', ' ', $type)),
                'message' => 'Test.',
            ]);
        }

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications/center?per_page=100');

        $items = collect($response->json('data.items'));
        foreach ($types as $type => $expected) {
            $item = $items->firstWhere('notification_type', $type);
            $this->assertNotNull($item, "Missing type: {$type}");
            $this->assertEquals($expected['icon'], $item['icon'], "Wrong icon for {$type}");
            $this->assertEquals($expected['severity'], $item['severity'], "Wrong severity for {$type}");
        }
    }
}
