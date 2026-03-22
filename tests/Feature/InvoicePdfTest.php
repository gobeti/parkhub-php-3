<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    private function createBookingForUser(User $user): Booking
    {
        $lot = ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'A1',
            'status' => 'available',
        ]);

        return Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $lot->name,
            'slot_number' => $slot->slot_number,
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(3),
            'status' => 'confirmed',
            'booking_type' => 'single',
        ]);
    }

    public function test_invoice_pdf_slash_endpoint_returns_pdf(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $booking = $this->createBookingForUser($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get("/api/v1/bookings/{$booking->id}/invoice/pdf");

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'pdf',
            strtolower($response->headers->get('Content-Type') ?? '')
        );
    }

    public function test_invoice_dot_pdf_endpoint_returns_pdf(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $booking = $this->createBookingForUser($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get("/api/v1/bookings/{$booking->id}/invoice.pdf");

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'pdf',
            strtolower($response->headers->get('Content-Type') ?? '')
        );
    }

    public function test_invoice_number_format_matches_inv_year_hex(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $booking = $this->createBookingForUser($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get("/api/v1/bookings/{$booking->id}/invoice");

        $response->assertStatus(200);
        $content = $response->getContent();

        // Invoice number: INV-{YEAR}-{HEX8}
        $year = date('Y');
        $shortId = strtoupper(substr(str_replace('-', '', $booking->id), 0, 8));
        $expectedInvoiceNo = "INV-{$year}-{$shortId}";
        $this->assertStringContainsString($expectedInvoiceNo, $content);
    }

    public function test_invoice_requires_authentication(): void
    {
        $user = User::factory()->create();
        $booking = $this->createBookingForUser($user);

        $response = $this->getJson("/api/v1/bookings/{$booking->id}/invoice/pdf");

        $response->assertStatus(401);
    }

    public function test_invoice_not_accessible_by_other_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $booking = $this->createBookingForUser($owner);
        $token = $other->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get("/api/v1/bookings/{$booking->id}/invoice/pdf");

        // Should 404 because query scopes to user_id
        $response->assertStatus(404);
    }

    public function test_invoice_for_nonexistent_booking_returns_404(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/v1/bookings/00000000-0000-0000-0000-000000000000/invoice/pdf');

        $response->assertStatus(404);
    }
}
