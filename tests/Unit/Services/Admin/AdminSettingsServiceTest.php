<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Admin;

use App\Models\Absence;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Webhook;
use App\Services\Admin\AdminSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_settings_writes_only_allowlisted_keys(): void
    {
        $service = app(AdminSettingsService::class);

        $count = $service->updateSettings([
            'company_name' => 'Acme Parking',
            'max_bookings_per_day' => 5,
            // Must be silently ignored — outside the allowlist.
            'smtp_pass' => 'leaked-secret',
            'admin_override' => 'yes',
        ]);

        $this->assertSame(2, $count);
        $this->assertSame('Acme Parking', Setting::get('company_name'));
        $this->assertSame('5', Setting::get('max_bookings_per_day'));
        $this->assertDatabaseMissing('settings', ['key' => 'smtp_pass']);
        $this->assertDatabaseMissing('settings', ['key' => 'admin_override']);
    }

    public function test_update_settings_normalises_booleans_to_strings(): void
    {
        $service = app(AdminSettingsService::class);

        $service->updateSettings([
            'allow_guest_bookings' => true,
            'self_registration' => false,
        ]);

        $this->assertSame('true', Setting::get('allow_guest_bookings'));
        $this->assertSame('false', Setting::get('self_registration'));
    }

    public function test_update_branding_writes_prefixed_and_canonical_keys(): void
    {
        $service = app(AdminSettingsService::class);

        $service->updateBranding([
            'company_name' => 'Nuan Ju',
            'primary_color' => '#0d9488',
            'use_case' => 'rental',
        ]);

        $this->assertSame('Nuan Ju', Setting::get('brand_company_name'));
        $this->assertSame('#0d9488', Setting::get('brand_primary_color'));
        $this->assertSame('rental', Setting::get('brand_use_case'));
        // Non-prefixed aliases must also be written for the two header fields.
        $this->assertSame('Nuan Ju', Setting::get('company_name'));
        $this->assertSame('rental', Setting::get('use_case'));
    }

    public function test_update_email_encrypts_smtp_password_at_rest(): void
    {
        $service = app(AdminSettingsService::class);

        $service->updateEmail([
            'smtp_host' => 'smtp.example.org',
            'smtp_pass' => 'plain-text-password',
            'enabled' => true,
        ]);

        $stored = Setting::get('smtp_pass');
        $this->assertIsString($stored);
        $this->assertNotSame('plain-text-password', $stored);
        $this->assertSame('plain-text-password', Crypt::decryptString($stored));
        $this->assertSame('smtp.example.org', Setting::get('smtp_host'));
        $this->assertSame('true', Setting::get('email_enabled'));
    }

    public function test_replace_webhooks_rejects_first_internal_url_without_deleting_existing(): void
    {
        $service = app(AdminSettingsService::class);

        Webhook::create([
            'url' => 'https://existing.example.com/hook',
            'events' => ['booking.created'],
            'secret' => 'seed',
            'active' => true,
        ]);

        $rejected = $service->replaceWebhooks(
            [
                ['url' => 'https://valid.example.com/one', 'events' => ['booking.created']],
                ['url' => 'http://169.254.169.254/metadata', 'events' => ['booking.cancelled']],
            ],
            fn (string $url) => ! str_contains($url, '169.254'),
        );

        $this->assertSame('http://169.254.169.254/metadata', $rejected);
        // Must NOT have deleted the existing webhooks on rejection.
        $this->assertSame(1, Webhook::query()->count());
        $this->assertDatabaseHas('webhooks', ['url' => 'https://existing.example.com/hook']);
    }

    public function test_replace_webhooks_replaces_all_rows_on_success(): void
    {
        $service = app(AdminSettingsService::class);

        Webhook::create([
            'url' => 'https://stale.example.com/hook',
            'events' => ['booking.created'],
            'active' => true,
        ]);

        $rejected = $service->replaceWebhooks(
            [
                ['url' => 'https://one.example.com/hook', 'events' => ['booking.created'], 'secret' => 's1', 'active' => true],
                ['url' => 'https://two.example.com/hook', 'events' => ['booking.cancelled'], 'secret' => 's2', 'active' => false],
            ],
            fn (string $url) => true,
        );

        $this->assertNull($rejected);
        $this->assertSame(2, Webhook::query()->count());
        $this->assertDatabaseMissing('webhooks', ['url' => 'https://stale.example.com/hook']);
        $this->assertDatabaseHas('webhooks', ['url' => 'https://one.example.com/hook']);
        $this->assertDatabaseHas('webhooks', ['url' => 'https://two.example.com/hook']);
    }

    public function test_update_impressum_writes_mapped_keys_and_emits_audit_log(): void
    {
        $service = app(AdminSettingsService::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $service->updateImpressum([
            'provider_name' => 'Acme GmbH',
            'street' => 'Hauptstr. 1',
            'vat_id' => 'DE123456789',
            // Keys not in the map must be ignored.
            'extra_marketing_banner' => 'whoops',
        ], $admin, '203.0.113.7');

        $this->assertSame('Acme GmbH', Setting::get('impressum_provider_name'));
        $this->assertSame('Hauptstr. 1', Setting::get('impressum_street'));
        $this->assertSame('DE123456789', Setting::get('impressum_vat_id'));
        $this->assertDatabaseMissing('settings', ['key' => 'impressum_extra_marketing_banner']);
        $this->assertDatabaseMissing('settings', ['key' => 'extra_marketing_banner']);
        $this->assertDatabaseHas('audit_log', [
            'user_id' => $admin->id,
            'action' => 'impressum_updated',
            'ip_address' => '203.0.113.7',
        ]);
    }

    public function test_reset_database_keeps_admin_and_purges_user_data(): void
    {
        $service = app(AdminSettingsService::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Lot A', 'address' => 'x', 'total_slots' => 1]);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'occupied']);
        Vehicle::create(['user_id' => $user->id, 'plate' => 'X-1', 'make' => 'VW']);
        Absence::create([
            'user_id' => $user->id,
            'absence_type' => 'vacation',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);
        Booking::factory()->create(['user_id' => $user->id]);

        $counts = $service->resetDatabase($admin);

        $this->assertGreaterThanOrEqual(1, $counts['bookings']);
        $this->assertSame(1, $counts['absences']);
        $this->assertSame(1, $counts['vehicles']);
        $this->assertSame(1, $counts['users']);
        $this->assertSame(0, Booking::query()->count());
        $this->assertSame(0, Vehicle::query()->count());
        $this->assertSame(0, Absence::query()->count());
        // User model uses SoftDeletes — the non-admin user must be soft-deleted,
        // the admin must remain active, and the default (non-trashed) scope
        // must only see the admin.
        $this->assertSame(1, User::query()->count());
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'deleted_at' => null]);
        $this->assertNotNull(User::withTrashed()->find($user->id)?->deleted_at);
        $this->assertSame('available', ParkingSlot::query()->value('status'));
        $this->assertDatabaseHas('audit_log', [
            'user_id' => $admin->id,
            'action' => 'database_reset',
        ]);
    }

    public function test_import_backup_only_restores_allowlisted_keys_and_audits(): void
    {
        $service = app(AdminSettingsService::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $imported = $service->importBackup(
            [
                'company_name' => 'Restored Corp',
                'primary_color' => '#112233',
                'max_bookings_per_day' => 7,
                // Not on the allowlist — must not be restored.
                'smtp_pass' => 'leaked',
                'admin_override' => 'yes',
            ],
            $admin,
            '203.0.113.9',
        );

        $this->assertSame(3, $imported);
        $this->assertSame('Restored Corp', Setting::get('company_name'));
        $this->assertSame('#112233', Setting::get('primary_color'));
        $this->assertSame('7', Setting::get('max_bookings_per_day'));
        $this->assertDatabaseMissing('settings', ['key' => 'smtp_pass']);
        $this->assertDatabaseMissing('settings', ['key' => 'admin_override']);

        $audit = DB::table('audit_log')
            ->where('user_id', $admin->id)
            ->where('action', 'settings_restored')
            ->first();
        $this->assertNotNull($audit);
        $decoded = is_string($audit->details) ? json_decode($audit->details, true) : $audit->details;
        $this->assertSame(3, $decoded['settings_count']);
    }

    public function test_use_case_theme_falls_back_to_personal_for_unknown_key(): void
    {
        $service = app(AdminSettingsService::class);

        $theme = $service->useCaseTheme('totally-unknown');

        $this->assertSame('personal', $theme['key']);
        $this->assertContains('guest_parking', $theme['features_emphasis']);
    }

    public function test_available_use_cases_is_five_themes_in_canonical_order(): void
    {
        $service = app(AdminSettingsService::class);

        $themes = $service->availableUseCases();

        $this->assertCount(5, $themes);
        $this->assertSame(
            ['company', 'residential', 'shared', 'rental', 'personal'],
            array_column($themes, 'key'),
        );
    }
}
