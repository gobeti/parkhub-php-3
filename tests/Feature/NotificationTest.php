<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_notifications(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        Notification::create([
            'user_id' => $user->id,
            'type' => 'booking_confirmed',
            'title' => 'Booking Confirmed',
            'message' => 'Your booking has been confirmed.',
        ]);

        Notification::create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Welcome',
            'message' => 'Welcome to ParkHub!',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_user_only_sees_own_notifications(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Notification::create([
            'user_id' => $user1->id,
            'type' => 'info',
            'title' => 'For User 1',
            'message' => 'Private notification.',
        ]);

        Notification::create([
            'user_id' => $user2->id,
            'type' => 'info',
            'title' => 'For User 2',
            'message' => 'Another private notification.',
        ]);

        $token = $user1->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'info',
            'title' => 'Unread',
            'message' => 'Please read me.',
            'read' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/notifications/'.$notification->id.'/read');

        $response->assertStatus(200);
        $this->assertDatabaseHas('notifications_custom', [
            'id' => $notification->id,
            'read' => true,
        ]);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $notification = Notification::create([
            'user_id' => $owner->id,
            'type' => 'info',
            'title' => 'Secret',
            'message' => 'Not yours.',
            'read' => false,
        ]);

        $token = $attacker->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/notifications/'.$notification->id.'/read');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        $response = $this->getJson('/api/v1/notifications');

        $response->assertStatus(401);
    }

    public function test_notifications_ordered_by_newest_first(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Create older notification first, then travel forward for the newer one
        $this->travel(-1)->hours();
        Notification::create([
            'user_id' => $user->id,
            'type' => 'info',
            'title' => 'Older',
            'message' => 'First created.',
        ]);

        $this->travelBack();
        Notification::create([
            'user_id' => $user->id,
            'type' => 'info',
            'title' => 'Newer',
            'message' => 'Second created.',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals('Newer', $data[0]['title']);
        $this->assertEquals('Older', $data[1]['title']);
    }
}
