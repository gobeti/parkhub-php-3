<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataImportExportTest extends TestCase
{
    use RefreshDatabase;

    private function enableModule(): void
    {
        config(['modules.data_import' => true]);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_import_users_csv(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $csv = "username,email,name,role,password\njohn,john@test.com,John Doe,user,secret123\njane,jane@test.com,Jane Doe,premium,secret456";
        $data = base64_encode($csv);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/import/users', [
            'format' => 'csv',
            'data' => $data,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertEquals(2, $response->json('data.imported'));
        $this->assertEquals(0, $response->json('data.skipped'));
    }

    public function test_import_users_skips_duplicates(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        User::factory()->create(['email' => 'existing@test.com', 'username' => 'existing']);

        $csv = "username,email,name,role,password\nexisting,existing@test.com,Existing User,user,secret";
        $data = base64_encode($csv);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/import/users', [
            'format' => 'csv',
            'data' => $data,
        ]);

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.imported'));
        $this->assertEquals(1, $response->json('data.skipped'));
        $this->assertCount(1, $response->json('data.errors'));
    }

    public function test_import_lots_csv(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $csv = "name,address,total_slots,hourly_rate,daily_max,currency\nLot A,123 Main St,50,2.50,20,EUR\nLot B,456 Oak Ave,30,3.00,25,USD";
        $data = base64_encode($csv);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/import/lots', [
            'format' => 'csv',
            'data' => $data,
        ]);

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.imported'));
        $this->assertDatabaseHas('parking_lots', ['name' => 'Lot A', 'total_slots' => 50]);
    }

    public function test_import_requires_admin(): void
    {
        $this->enableModule();
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->postJson('/api/v1/admin/import/users', [
            'format' => 'csv',
            'data' => base64_encode('test'),
        ])->assertForbidden();
    }

    public function test_export_users_returns_csv(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get('/api/v1/admin/data/export/users');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_export_lots_returns_csv(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);

        $response = $this->actingAs($admin)->get('/api/v1/admin/data/export/lots');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_export_bookings_returns_csv(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get('/api/v1/admin/data/export/bookings');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_import_validation_requires_format(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/import/users', [
            'data' => base64_encode('test'),
        ]);

        $response->assertStatus(422);
    }

    public function test_data_import_module_disabled_returns_404(): void
    {
        config(['modules.data_import' => false]);
        $admin = $this->adminUser();

        $this->actingAs($admin)->postJson('/api/v1/admin/import/users', [
            'format' => 'csv',
            'data' => base64_encode('test'),
        ])->assertNotFound();
    }
}
