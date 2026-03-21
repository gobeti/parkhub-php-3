<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DemoResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['parkhub.demo_mode' => true]);
        Cache::flush();
    }

    /**
     * Mock Artisan so migrate:fresh and db:seed don't destroy the test DB.
     */
    private function mockArtisan(): void
    {
        Artisan::shouldReceive('call')
            ->with('migrate:fresh', ['--force' => true])
            ->andReturn(0);
        Artisan::shouldReceive('call')
            ->with('db:seed', ['--class' => 'ProductionSimulationSeeder', '--force' => true])
            ->andReturn(0);
    }

    public function test_status_returns_reset_tracking_fields_initially_null(): void
    {
        $response = $this->getJson('/api/v1/demo/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'last_reset_at',
                    'next_scheduled_reset',
                    'reset_in_progress',
                ],
            ])
            ->assertJsonPath('data.last_reset_at', null)
            ->assertJsonPath('data.next_scheduled_reset', null)
            ->assertJsonPath('data.reset_in_progress', false);
    }

    public function test_status_shows_tracking_after_reset(): void
    {
        $this->mockArtisan();

        // Solo reset (no other viewers)
        $this->postJson('/api/v1/demo/reset')->assertStatus(200);

        $response = $this->getJson('/api/v1/demo/status');

        $response->assertStatus(200)
            ->assertJsonPath('data.reset_in_progress', false);

        $data = $response->json('data');
        $this->assertNotNull($data['last_reset_at']);
        $this->assertNotNull($data['next_scheduled_reset']);

        // Verify valid ISO 8601 timestamps
        $lastReset = strtotime($data['last_reset_at']);
        $nextReset = strtotime($data['next_scheduled_reset']);
        $this->assertNotFalse($lastReset);
        $this->assertNotFalse($nextReset);

        // next_scheduled_reset should be 6 hours after last_reset_at
        $this->assertEquals(6 * 3600, $nextReset - $lastReset);
    }

    public function test_solo_reset_succeeds_with_no_viewers(): void
    {
        $this->mockArtisan();

        $response = $this->postJson('/api/v1/demo/reset');

        $response->assertStatus(200)
            ->assertJsonPath('data.reset', true)
            ->assertJsonPath('data.votes', 0);
    }

    public function test_reset_sets_cache_tracking_values(): void
    {
        $this->mockArtisan();

        $this->postJson('/api/v1/demo/reset')->assertStatus(200);

        // reset_in_progress should be cleared
        $this->assertFalse((bool) Cache::get('demo_reset_in_progress', false));

        // last_reset_at should be set
        $lastReset = Cache::get('demo_last_reset_at');
        $this->assertNotNull($lastReset);

        // next_scheduled_reset should be set
        $nextReset = Cache::get('demo_next_scheduled_reset');
        $this->assertNotNull($nextReset);

        // Interval is 6 hours
        $this->assertEquals(6 * 3600, $nextReset - $lastReset);
    }

    public function test_vote_threshold_triggers_reset_and_tracking(): void
    {
        $this->mockArtisan();

        // Vote from 3 different IPs to reach threshold (default = 3)
        $this->postJson('/api/v1/demo/vote', [], ['REMOTE_ADDR' => '10.0.0.1']);
        $this->postJson('/api/v1/demo/vote', [], ['REMOTE_ADDR' => '10.0.0.2']);
        $response = $this->postJson('/api/v1/demo/vote', [], ['REMOTE_ADDR' => '10.0.0.3']);

        $response->assertStatus(200)
            ->assertJsonPath('data.reset', true);

        // Cache should reflect completed reset
        $this->assertNotNull(Cache::get('demo_last_reset_at'));
        $this->assertNotNull(Cache::get('demo_next_scheduled_reset'));
        $this->assertFalse((bool) Cache::get('demo_reset_in_progress', false));
    }

    public function test_votes_cleared_after_reset(): void
    {
        $this->mockArtisan();

        // Cast a vote first
        $this->postJson('/api/v1/demo/vote');

        // Trigger reset
        $this->postJson('/api/v1/demo/reset')->assertStatus(200);

        // Votes should be cleared
        $response = $this->getJson('/api/v1/demo/status');
        $response->assertJsonPath('data.votes.current', 0);
    }

    public function test_reset_returns_500_on_migration_failure(): void
    {
        Artisan::shouldReceive('call')
            ->with('migrate:fresh', ['--force' => true])
            ->andThrow(new \Exception('Migration failed'));

        $response = $this->postJson('/api/v1/demo/reset');

        $response->assertStatus(500)
            ->assertJsonPath('data', null);

        // reset_in_progress should be cleared even on failure
        $this->assertFalse((bool) Cache::get('demo_reset_in_progress', false));
    }

    public function test_reset_blocked_with_multiple_viewers(): void
    {
        $viewers = [
            '127.0.0.1' => now()->timestamp,
            '192.168.1.100' => now()->timestamp,
        ];
        Cache::put('demo_viewers', $viewers, 600);

        $response = $this->postJson('/api/v1/demo/reset');

        $response->assertStatus(409);
    }

    public function test_status_disabled_returns_403(): void
    {
        config(['parkhub.demo_mode' => false]);

        $this->getJson('/api/v1/demo/status')
            ->assertStatus(403);
    }
}
