<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Audit;

use App\Models\AuditLog;
use App\Services\Audit\AuditLogQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuditLogQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AuditLogQueryService
    {
        return app(AuditLogQueryService::class);
    }

    private function seedEntries(): void
    {
        AuditLog::log(['action' => 'LoginSuccess', 'event_type' => 'LoginSuccess', 'username' => 'admin', 'ip_address' => '10.0.0.1']);
        AuditLog::log(['action' => 'BookingCreated', 'event_type' => 'BookingCreated', 'username' => 'alice', 'ip_address' => '10.0.0.2', 'details' => ['slot' => 'A1']]);
        AuditLog::log(['action' => 'SettingsChanged', 'event_type' => 'SettingsChanged', 'username' => 'admin', 'ip_address' => '10.0.0.1']);
    }

    public function test_paginate_clamps_per_page_to_max_and_reports_total(): void
    {
        $this->seedEntries();

        // per_page=500 must clamp down to MAX_PER_PAGE (100).
        $request = Request::create('/audit-log', 'GET', ['per_page' => '500']);
        $result = $this->service()->paginate($request);

        $this->assertSame(AuditLogQueryService::MAX_PER_PAGE, $result['per_page']);
        $this->assertSame(3, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(1, $result['total_pages']);
        $this->assertCount(3, $result['entries']);
    }

    public function test_paginate_defaults_when_params_missing(): void
    {
        $this->seedEntries();

        $result = $this->service()->paginate(Request::create('/audit-log', 'GET'));

        $this->assertSame(AuditLogQueryService::DEFAULT_PER_PAGE, $result['per_page']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(3, $result['total']);
    }

    public function test_paginate_filters_by_action_and_user(): void
    {
        $this->seedEntries();

        $byAction = $this->service()->paginate(
            Request::create('/audit-log', 'GET', ['action' => 'LoginSuccess'])
        );
        $this->assertSame(1, $byAction['total']);
        $this->assertSame('LoginSuccess', $byAction['entries'][0]['event_type']);

        $byUser = $this->service()->paginate(
            Request::create('/audit-log', 'GET', ['user' => 'alice'])
        );
        $this->assertSame(1, $byUser['total']);
        $this->assertSame('alice', $byUser['entries'][0]['username']);
    }

    public function test_paginate_stringifies_array_details(): void
    {
        $this->seedEntries();

        $result = $this->service()->paginate(
            Request::create('/audit-log', 'GET', ['action' => 'BookingCreated'])
        );

        $this->assertSame(1, $result['total']);
        // Array `details` must be JSON-encoded so API consumers receive a scalar string.
        $this->assertSame('{"slot":"A1"}', $result['entries'][0]['details']);
    }

    public function test_filtered_query_applies_date_range_and_user_id(): void
    {
        // Two entries, same user_id, different days.
        $old = AuditLog::log(['action' => 'x', 'event_type' => 'x', 'user_id' => 'u-1', 'username' => 'u']);
        $new = AuditLog::log(['action' => 'y', 'event_type' => 'y', 'user_id' => 'u-1', 'username' => 'u']);
        $other = AuditLog::log(['action' => 'z', 'event_type' => 'z', 'user_id' => 'u-2', 'username' => 'other']);

        $this->assertNotNull($old);
        $this->assertNotNull($new);
        $this->assertNotNull($other);

        // Rewind the older entry a day before "today".
        $old->created_at = now()->subDay();
        $old->save();

        $today = date('Y-m-d');
        $request = Request::create('/audit-log', 'GET', [
            'user_id' => 'u-1',
            'from' => $today,
            'to' => $today,
        ]);

        $entries = $this->service()->filteredQuery($request)->get();

        $this->assertCount(1, $entries);
        $this->assertSame($new->id, $entries->first()->id);
    }

    public function test_stream_csv_contains_header_and_row_for_each_entry(): void
    {
        $this->seedEntries();

        $response = $this->service()->streamCsv(Request::create('/audit-log/export', 'GET'));
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('Timestamp,"Event Type","User ID",Username', $csv);
        // Each of the 3 seeded entries must appear on its own line.
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertCount(4, $lines); // 1 header + 3 rows
        $this->assertStringContainsString('LoginSuccess', $csv);
        $this->assertStringContainsString('BookingCreated', $csv);
        $this->assertStringContainsString('SettingsChanged', $csv);
    }

    public function test_stream_json_returns_count_and_exported_at_envelope(): void
    {
        $this->seedEntries();

        $response = $this->service()->streamJson(
            Request::create('/audit-log/export/enhanced', 'GET', ['format' => 'json', 'action' => 'BookingCreated'])
        );
        ob_start();
        $response->sendContent();
        $payload = json_decode((string) ob_get_clean(), true);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('exported_at', $payload);
        $this->assertSame(1, $payload['count']);
        $this->assertCount(1, $payload['entries']);
        $this->assertSame('BookingCreated', $payload['entries'][0]['event_type']);
    }

    public function test_stream_pdf_emits_plain_text_summary(): void
    {
        $this->seedEntries();

        $response = $this->service()->streamPdf(
            Request::create('/audit-log/export/enhanced', 'GET', ['format' => 'pdf'])
        );
        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();

        $this->assertStringContainsString('AUDIT LOG EXPORT', $body);
        $this->assertStringContainsString('Entries: 3', $body);
        $this->assertStringContainsString('LoginSuccess', $body);
    }
}
