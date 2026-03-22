<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsenceApprovalControllerTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_user_can_submit_absence_request(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/absences/requests', [
                'absence_type' => 'vacation',
                'start_date' => now()->addDays(5)->format('Y-m-d'),
                'end_date' => now()->addDays(10)->format('Y-m-d'),
                'reason' => 'Family holiday',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['status' => 'pending']);
        $this->assertDatabaseHas('absences', [
            'user_id' => $user->id,
            'status' => 'pending',
            'source' => 'approval_request',
        ]);
    }

    public function test_submit_requires_reason(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/absences/requests', [
                'absence_type' => 'sick',
                'start_date' => now()->addDay()->format('Y-m-d'),
                'end_date' => now()->addDays(2)->format('Y-m-d'),
            ]);

        $response->assertStatus(422);
    }

    public function test_user_can_view_my_requests(): void
    {
        $user = User::factory()->create();

        Absence::create([
            'user_id' => $user->id,
            'absence_type' => 'vacation',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
            'note' => 'Trip',
            'source' => 'approval_request',
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/absences/my');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['status' => 'pending']);
    }

    public function test_admin_can_list_pending(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        Absence::create([
            'user_id' => $user->id,
            'absence_type' => 'homeoffice',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
            'note' => 'WFH',
            'source' => 'approval_request',
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/v1/admin/absences/pending');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_non_admin_cannot_list_pending(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/admin/absences/pending');

        $response->assertStatus(403);
    }

    public function test_admin_can_approve_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $absence = Absence::create([
            'user_id' => $user->id,
            'absence_type' => 'vacation',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'note' => 'Vacation',
            'source' => 'approval_request',
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/v1/admin/absences/{$absence->id}/approve", [
                'comment' => 'Enjoy your time off!',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('absences', [
            'id' => $absence->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewer_comment' => 'Enjoy your time off!',
        ]);
    }

    public function test_admin_can_reject_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $absence = Absence::create([
            'user_id' => $user->id,
            'absence_type' => 'training',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
            'note' => 'Conference',
            'source' => 'approval_request',
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/v1/admin/absences/{$absence->id}/reject", [
                'reason' => 'Team meeting conflict',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('absences', [
            'id' => $absence->id,
            'status' => 'rejected',
            'reviewer_comment' => 'Team meeting conflict',
        ]);
    }

    public function test_reject_requires_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $absence = Absence::create([
            'user_id' => $user->id,
            'absence_type' => 'vacation',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
            'note' => 'Holiday',
            'source' => 'approval_request',
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/v1/admin/absences/{$absence->id}/reject", []);

        $response->assertStatus(422);
    }

    public function test_all_absence_types_accepted(): void
    {
        $user = User::factory()->create();
        $types = ['homeoffice', 'vacation', 'sick', 'training', 'business_trip', 'personal', 'other'];

        foreach ($types as $type) {
            $response = $this->withHeaders($this->authHeader($user))
                ->postJson('/api/v1/absences/requests', [
                    'absence_type' => $type,
                    'start_date' => now()->addDays(5)->format('Y-m-d'),
                    'end_date' => now()->addDays(6)->format('Y-m-d'),
                    'reason' => "Testing $type",
                ]);

            $response->assertStatus(201);
        }

        $this->assertDatabaseCount('absences', 7);
    }
}
