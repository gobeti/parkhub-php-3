<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\Booking;
use App\Models\Favorite;
use App\Models\Notification;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_preferences_default_empty(): void
    {
        $user = User::factory()->create(['preferences' => null]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user/preferences');

        $response->assertStatus(200);
    }

    public function test_update_preferences_theme(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/user/preferences', ['theme' => 'dark'])
            ->assertStatus(200);

        $prefs = $user->fresh()->preferences;
        $this->assertEquals('dark', $prefs['theme']);
    }

    public function test_update_preferences_invalid_theme_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/user/preferences', ['theme' => 'invalid_theme'])
            ->assertStatus(422);
    }

    public function test_update_preferences_language(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/user/preferences', ['language' => 'de'])
            ->assertStatus(200);

        $prefs = $user->fresh()->preferences;
        $this->assertEquals('de', $prefs['language']);
    }

    public function test_user_stats_with_no_bookings(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user/stats');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_bookings', 0)
            ->assertJsonPath('data.avg_duration_minutes', 0);
    }

    public function test_user_stats_with_bookings(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Stats Lot', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'S1', 'status' => 'available']);
        $token = $user->createToken('test')->plainTextToken;

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'slot_number' => 'S1',
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
            'booking_type' => 'single',
            'status' => 'completed',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user/stats');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.total_bookings'));
        $this->assertGreaterThan(0, $response->json('data.avg_duration_minutes'));
    }

    public function test_user_favorites_empty(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user/favorites')
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_add_favorite_requires_slot_id(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/user/favorites', [])
            ->assertStatus(422);
    }

    public function test_add_favorite_idempotent(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Fav Lot', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'F1', 'status' => 'available']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/user/favorites', ['slot_id' => $slot->id])
            ->assertStatus(201);

        // Adding again should not create duplicate
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/user/favorites', ['slot_id' => $slot->id])
            ->assertStatus(201);

        $this->assertEquals(1, Favorite::where('user_id', $user->id)->count());
    }

    public function test_remove_favorite(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Rm Lot', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'R1', 'status' => 'available']);
        $token = $user->createToken('test')->plainTextToken;

        Favorite::create(['user_id' => $user->id, 'slot_id' => $slot->id]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/user/favorites/'.$slot->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'slot_id' => $slot->id]);
    }

    public function test_notifications_empty(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user/notifications')
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_mark_notification_read(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $notif = Notification::create([
            'user_id' => $user->id,
            'type' => 'booking_confirmed',
            'title' => 'Booking Confirmed',
            'message' => 'Your booking is confirmed',
            'read' => false,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/user/notifications/'.$notif->id.'/read')
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications_custom', ['id' => $notif->id, 'read' => true]);
    }

    public function test_mark_other_users_notification_fails(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $token2 = $user2->createToken('test')->plainTextToken;

        $notif = Notification::create([
            'user_id' => $user1->id,
            'type' => 'info',
            'title' => 'Info',
            'message' => 'Private',
            'read' => false,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token2)
            ->putJson('/api/user/notifications/'.$notif->id.'/read')
            ->assertStatus(404);
    }

    public function test_gdpr_export(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user/export');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['exported_at', 'profile', 'bookings', 'absences', 'vehicles']]);
    }

    public function test_calendar_export_requires_auth(): void
    {
        $this->getJson('/api/v1/user/calendar.ics')
            ->assertStatus(401);
    }

    public function test_mark_all_notifications_read(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        Notification::create(['user_id' => $user->id, 'type' => 'info', 'title' => 'A', 'message' => 'A', 'read' => false]);
        Notification::create(['user_id' => $user->id, 'type' => 'info', 'title' => 'B', 'message' => 'B', 'read' => false]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/notifications/read-all')
            ->assertStatus(200);

        $this->assertEquals(0, Notification::where('user_id', $user->id)->where('read', false)->count());
    }

    public function test_update_preferences_merges_existing(): void
    {
        $user = User::factory()->create(['preferences' => ['language' => 'en', 'theme' => 'light']]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/user/preferences', ['theme' => 'dark'])
            ->assertStatus(200);

        $prefs = $user->fresh()->preferences;
        $this->assertEquals('dark', $prefs['theme']);
        $this->assertEquals('en', $prefs['language']); // preserved
    }
}
