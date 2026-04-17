<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Reports;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Services\Reports\ReportExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ReportExportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_bookings_csv_emits_header_and_one_row_per_booking(): void
    {
        $user = User::factory()->create(['name' => 'Alice Example']);
        $lot = ParkingLot::create([
            'name' => 'CSV Lot',
            'total_slots' => 1,
            'available_slots' => 1,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'R1',
            'status' => 'available',
        ]);
        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $lot->name,
            'slot_number' => $slot->slot_number,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
            'status' => Booking::STATUS_CONFIRMED,
            'booking_type' => 'einmalig',
            'vehicle_plate' => 'B-AA-1234',
        ]);

        $response = app(ReportExportService::class)->exportBookingsCsv();
        $csv = $this->captureStream($response);

        $this->assertSame('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('ID,User,Lot,Slot,Vehicle,Start,End,Status,Type', $csv);
        $this->assertStringContainsString('Alice Example', $csv);
        $this->assertStringContainsString('B-AA-1234', $csv);
        $this->assertStringContainsString('CSV Lot', $csv);
    }

    public function test_export_bookings_csv_has_header_only_when_no_bookings(): void
    {
        $response = app(ReportExportService::class)->exportBookingsCsv();
        $csv = $this->captureStream($response);

        // One header line followed by no data rows
        $lines = array_filter(explode("\n", trim($csv)));
        $this->assertCount(1, $lines, 'expected only the header row');
    }

    public function test_export_users_csv_protects_against_formula_injection(): void
    {
        User::factory()->create([
            'username' => 'legit',
            'name' => '=SUM(A1:A2)', // classic CSV injection payload
            'email' => 'legit@example.com',
        ]);

        $response = app(ReportExportService::class)->exportUsersCsv();
        $csv = (string) $response->getContent();

        $this->assertSame('text/csv', $response->headers->get('Content-Type'));
        // csvSafe prefixes formula sigils with a single quote; fputcsv leaves
        // it unquoted when no comma/newline is present in the cell itself.
        $this->assertStringContainsString(",'=SUM(A1:A2),", $csv);
    }

    /**
     * Stream download responses only write when `sendContent` is called.
     */
    private function captureStream(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }
}
