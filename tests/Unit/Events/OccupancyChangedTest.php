<?php

namespace Tests\Unit\Events;

use App\Events\OccupancyChanged;
use App\Models\ParkingLot;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OccupancyChangedTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_stores_lot_and_counts(): void
    {
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 10]);
        $event = new OccupancyChanged($lot, available: 7, total: 10);

        $this->assertSame($lot, $event->lot);
        $this->assertEquals(7, $event->available);
        $this->assertEquals(10, $event->total);
    }

    public function test_broadcasts_on_public_channel(): void
    {
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 10]);
        $event = new OccupancyChanged($lot, available: 7, total: 10);

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
    }

    public function test_broadcast_as_returns_correct_name(): void
    {
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 10]);
        $event = new OccupancyChanged($lot, available: 7, total: 10);
        $this->assertEquals('occupancy.changed', $event->broadcastAs());
    }

    public function test_broadcast_with_contains_lot_data(): void
    {
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 10]);
        $event = new OccupancyChanged($lot, available: 7, total: 10);

        $data = $event->broadcastWith();
        $this->assertArrayHasKey('lot_id', $data);
        $this->assertArrayHasKey('lot_name', $data);
        $this->assertArrayHasKey('available', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertEquals($lot->id, $data['lot_id']);
        $this->assertEquals('Test', $data['lot_name']);
        $this->assertEquals(7, $data['available']);
        $this->assertEquals(10, $data['total']);
    }
}
