<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_announcements_is_public(): void
    {
        Announcement::create([
            'title' => 'Public Notice',
            'message' => 'Parking lot maintenance this weekend',
            'severity' => 'info',
            'active' => true,
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
        ]);

        $response = $this->getJson('/api/v1/announcements/active');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Public Notice', $data[0]['title']);
    }

    public function test_inactive_announcements_are_excluded(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Announcement::create([
            'title' => 'Active One',
            'message' => 'Visible',
            'severity' => 'info',
            'active' => true,
            'created_by' => $admin->id,
        ]);

        Announcement::create([
            'title' => 'Inactive One',
            'message' => 'Hidden',
            'severity' => 'info',
            'active' => false,
            'created_by' => $admin->id,
        ]);

        $response = $this->getJson('/api/v1/announcements/active');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Active One', $data[0]['title']);
    }

    public function test_admin_can_list_all_announcements(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        Announcement::create([
            'title' => 'Ann 1',
            'message' => 'Body 1',
            'active' => true,
            'created_by' => $admin->id,
        ]);

        Announcement::create([
            'title' => 'Ann 2',
            'message' => 'Body 2',
            'active' => false,
            'created_by' => $admin->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/announcements');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_can_create_announcement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'New Announcement',
                'message' => 'Important update for all users',
                'severity' => 'warning',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('announcements', [
            'title' => 'New Announcement',
            'severity' => 'warning',
            'active' => true,
        ]);
    }

    public function test_admin_can_update_announcement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $ann = Announcement::create([
            'title' => 'Original',
            'message' => 'Original body',
            'active' => true,
            'created_by' => $admin->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/announcements/'.$ann->id, [
                'title' => 'Updated Title',
                'active' => false,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('announcements', [
            'id' => $ann->id,
            'title' => 'Updated Title',
            'active' => false,
        ]);
    }

    public function test_admin_can_delete_announcement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $ann = Announcement::create([
            'title' => 'To Delete',
            'message' => 'Will be removed',
            'active' => true,
            'created_by' => $admin->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/admin/announcements/'.$ann->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('announcements', ['id' => $ann->id]);
    }

    public function test_non_admin_cannot_create_announcement(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'Sneaky',
                'message' => 'Should not work',
            ])
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_delete_announcement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $ann = Announcement::create([
            'title' => 'Protected',
            'message' => 'Cannot delete',
            'active' => true,
            'created_by' => $admin->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/admin/announcements/'.$ann->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('announcements', ['id' => $ann->id]);
    }

    public function test_non_admin_cannot_list_admin_announcements(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/announcements')
            ->assertStatus(403);
    }

    public function test_announcement_creation_validates_required_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/announcements', [])
            ->assertStatus(422);
    }
}
