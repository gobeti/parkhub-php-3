<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeader(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return ['Authorization' => 'Bearer '.$admin->createToken('test')->plainTextToken];
    }

    public function test_admin_can_get_default_widget_layout(): void
    {
        $response = $this->withHeaders($this->adminHeader())
            ->getJson('/api/v1/admin/widgets');

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data' => ['user_id', 'widgets']]);
        $this->assertCount(8, $response->json('data.widgets'));
    }

    public function test_admin_can_save_widget_layout(): void
    {
        $widgets = [
            ['id' => 'w1', 'widget_type' => 'occupancy_chart', 'position' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4], 'visible' => true],
            ['id' => 'w2', 'widget_type' => 'revenue_summary', 'position' => ['x' => 6, 'y' => 0, 'w' => 6, 'h' => 4], 'visible' => false],
        ];

        $response = $this->withHeaders($this->adminHeader())
            ->putJson('/api/v1/admin/widgets', ['widgets' => $widgets]);

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data.widgets');
    }

    public function test_admin_can_get_widget_data(): void
    {
        $types = ['occupancy_chart', 'revenue_summary', 'recent_bookings', 'user_growth', 'booking_heatmap', 'active_alerts', 'maintenance_status', 'ev_charging_status'];

        foreach ($types as $type) {
            $response = $this->withHeaders($this->adminHeader())
                ->getJson("/api/v1/admin/widgets/data/$type");

            $response->assertStatus(200);
            $response->assertJsonStructure(['success', 'data' => ['widget_id', 'data']]);
            $response->assertJsonFragment(['widget_id' => $type]);
        }
    }

    public function test_invalid_widget_type_returns_404(): void
    {
        $response = $this->withHeaders($this->adminHeader())
            ->getJson('/api/v1/admin/widgets/data/invalid_widget');

        $response->assertStatus(404);
    }

    public function test_non_admin_cannot_access_widgets(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $header = ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];

        $this->withHeaders($header)->getJson('/api/v1/admin/widgets')->assertStatus(403);
        $this->withHeaders($header)->putJson('/api/v1/admin/widgets', ['widgets' => []])->assertStatus(403);
    }

    public function test_save_validates_widget_types(): void
    {
        $widgets = [
            ['id' => 'w1', 'widget_type' => 'nonexistent', 'position' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4], 'visible' => true],
        ];

        $response = $this->withHeaders($this->adminHeader())
            ->putJson('/api/v1/admin/widgets', ['widgets' => $widgets]);

        $response->assertStatus(422);
    }
}
