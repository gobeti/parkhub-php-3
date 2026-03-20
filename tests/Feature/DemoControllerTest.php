<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DemoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Enable demo mode for these tests
        config(['parkhub.demo_mode' => true]);
        Cache::flush();
    }

    public function test_demo_status_returns_timer_and_votes(): void
    {
        $response = $this->getJson('/api/v1/demo/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'enabled',
                    'timer' => ['remaining', 'duration', 'started_at'],
                    'votes' => ['current', 'threshold', 'has_voted'],
                    'viewers',
                    'last_reset_at',
                    'next_scheduled_reset',
                    'reset_in_progress',
                ],
            ])
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.votes.current', 0);
    }

    public function test_demo_status_disabled_returns_403(): void
    {
        config(['parkhub.demo_mode' => false]);

        $this->getJson('/api/v1/demo/status')
            ->assertStatus(403);
    }

    public function test_demo_vote_records_vote(): void
    {
        $response = $this->postJson('/api/v1/demo/vote');

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Vote recorded')
            ->assertJsonPath('data.votes', 1)
            ->assertJsonPath('data.threshold', 3);
    }

    public function test_demo_vote_duplicate_returns_already_voted(): void
    {
        // First vote
        $this->postJson('/api/v1/demo/vote');

        // Second vote from same IP
        $response = $this->postJson('/api/v1/demo/vote');

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Already voted');
    }

    public function test_demo_vote_disabled_returns_403(): void
    {
        config(['parkhub.demo_mode' => false]);

        $this->postJson('/api/v1/demo/vote')
            ->assertStatus(403);
    }

    public function test_demo_config_returns_mode(): void
    {
        $response = $this->getJson('/api/v1/demo/config');

        $response->assertStatus(200)
            ->assertJsonPath('data.demo_mode', true);
    }

    public function test_demo_config_disabled(): void
    {
        config(['parkhub.demo_mode' => false]);

        $response = $this->getJson('/api/v1/demo/config');

        $response->assertStatus(200)
            ->assertJsonPath('data.demo_mode', false);
    }

    public function test_demo_reset_disabled_returns_403(): void
    {
        config(['parkhub.demo_mode' => false]);

        $this->postJson('/api/v1/demo/reset')
            ->assertStatus(403);
    }

    public function test_demo_reset_blocked_with_multiple_viewers(): void
    {
        // Simulate multiple viewers by pre-populating the cache
        $viewers = [
            '127.0.0.1' => now()->timestamp,
            '192.168.1.100' => now()->timestamp,
        ];
        Cache::put('demo_viewers', $viewers, 600);

        $response = $this->postJson('/api/v1/demo/reset');

        $response->assertStatus(409);
    }
}
