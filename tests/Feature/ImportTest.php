<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeIcal(array $events): string
    {
        $lines = ["BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//Test//EN"];
        foreach ($events as $event) {
            $lines[] = "BEGIN:VEVENT";
            $lines[] = "DTSTART;VALUE=DATE:{$event['start']}";
            if (isset($event['end'])) {
                $lines[] = "DTEND;VALUE=DATE:{$event['end']}";
            }
            if (isset($event['summary'])) {
                $lines[] = "SUMMARY:{$event['summary']}";
            }
            $lines[] = "END:VEVENT";
        }
        $lines[] = "END:VCALENDAR";

        return implode("\r\n", $lines);
    }

    public function test_import_ical_with_single_event(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $ical = $this->makeIcal([
            ['start' => '20260401', 'end' => '20260405', 'summary' => 'Vacation week'],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/absences/import-ical', ['ical' => $ical]);

        $response->assertStatus(200)
            ->assertJsonPath('data.created', 1);

        $this->assertDatabaseHas('absences', [
            'user_id' => $user->id,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-05',
            'source' => 'import',
        ]);
    }

    public function test_import_ical_with_multiple_events(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $ical = $this->makeIcal([
            ['start' => '20260501', 'end' => '20260502', 'summary' => 'Urlaub'],
            ['start' => '20260510', 'end' => '20260512', 'summary' => 'Conference'],
            ['start' => '20260520', 'end' => '20260521', 'summary' => 'Vacation day'],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/absences/import-ical', ['ical' => $ical]);

        $response->assertStatus(200)
            ->assertJsonPath('data.created', 3);

        $this->assertDatabaseCount('absences', 3);
    }

    public function test_import_ical_vacation_keyword_sets_type(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $ical = $this->makeIcal([
            ['start' => '20260601', 'end' => '20260605', 'summary' => 'Summer Vacation 2026'],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/absences/import-ical', ['ical' => $ical]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('absences', [
            'user_id' => $user->id,
            'absence_type' => 'vacation',
        ]);
    }

    public function test_import_ical_requires_content(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/absences/import-ical', [])
            ->assertStatus(422);
    }

    public function test_import_ical_empty_calendar_creates_nothing(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nEND:VCALENDAR";

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/absences/import-ical', ['ical' => $ical]);

        $response->assertStatus(200)
            ->assertJsonPath('data.created', 0);

        $this->assertDatabaseCount('absences', 0);
    }

    public function test_import_ical_requires_authentication(): void
    {
        $this->postJson('/api/absences/import-ical', ['ical' => 'BEGIN:VCALENDAR'])
            ->assertStatus(401);
    }
}
