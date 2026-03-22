<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_cannot_register_visitor(): void
    {
        $response = $this->postJson('/api/v1/visitors/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'visit_date' => now()->addDay()->toISOString(),
        ]);

        $response->assertStatus(401);
    }

    public function test_register_visitor_successfully(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/visitors/register', [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'vehicle_plate' => 'ABC-123',
            'visit_date' => now()->addDay()->toISOString(),
            'purpose' => 'Business meeting',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Jane Smith')
            ->assertJsonPath('data.email', 'jane@example.com')
            ->assertJsonPath('data.vehicle_plate', 'ABC-123')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('visitors', [
            'name' => 'Jane Smith',
            'host_user_id' => $user->id,
        ]);
    }

    public function test_register_visitor_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/visitors/register', [
            'email' => 'not-valid',
        ]);

        $response->assertStatus(422);
    }

    public function test_list_own_visitors(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Visitor::create([
            'host_user_id' => $user->id,
            'name' => 'My Visitor',
            'email' => 'mine@example.com',
            'visit_date' => now()->addDay(),
            'status' => 'pending',
        ]);

        Visitor::create([
            'host_user_id' => $other->id,
            'name' => 'Other Visitor',
            'email' => 'other@example.com',
            'visit_date' => now()->addDay(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/visitors');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_list_all_visitors(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        Visitor::create([
            'host_user_id' => $admin->id,
            'name' => 'Admin Visitor',
            'email' => 'admin-v@example.com',
            'visit_date' => now()->addDay(),
            'status' => 'pending',
        ]);

        Visitor::create([
            'host_user_id' => $user->id,
            'name' => 'User Visitor',
            'email' => 'user-v@example.com',
            'visit_date' => now()->addDay(),
            'status' => 'checked_in',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/visitors');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_filter_visitors_by_status(): void
    {
        $admin = User::factory()->admin()->create();

        Visitor::create([
            'host_user_id' => $admin->id,
            'name' => 'Pending',
            'email' => 'p@example.com',
            'visit_date' => now()->addDay(),
            'status' => 'pending',
        ]);

        Visitor::create([
            'host_user_id' => $admin->id,
            'name' => 'Checked In',
            'email' => 'c@example.com',
            'visit_date' => now()->addDay(),
            'status' => 'checked_in',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/visitors?status=pending');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_check_in_visitor(): void
    {
        $user = User::factory()->create();

        $visitor = Visitor::create([
            'host_user_id' => $user->id,
            'name' => 'Checkin Test',
            'email' => 'checkin@example.com',
            'visit_date' => now()->addDay(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/visitors/{$visitor->id}/check-in");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'checked_in');

        $this->assertNotNull($visitor->fresh()->checked_in_at);
    }

    public function test_cannot_check_in_non_pending_visitor(): void
    {
        $user = User::factory()->create();

        $visitor = Visitor::create([
            'host_user_id' => $user->id,
            'name' => 'Already In',
            'email' => 'already@example.com',
            'visit_date' => now()->addDay(),
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/visitors/{$visitor->id}/check-in");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cancel_visitor(): void
    {
        $user = User::factory()->create();

        $visitor = Visitor::create([
            'host_user_id' => $user->id,
            'name' => 'Cancel Me',
            'email' => 'cancel@example.com',
            'visit_date' => now()->addDay(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/visitors/{$visitor->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals('cancelled', $visitor->fresh()->status);
    }

    public function test_disabled_visitors_module_returns_404(): void
    {
        config(['modules.visitors' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/visitors')->assertNotFound();
    }

    public function test_visitor_has_qr_code_on_registration(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/visitors/register', [
            'name' => 'QR Test',
            'email' => 'qr@example.com',
            'visit_date' => now()->addDay()->toISOString(),
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertNotNull($data['qr_code']);
        $this->assertStringStartsWith('data:image/png;base64,', $data['qr_code']);
    }
}
