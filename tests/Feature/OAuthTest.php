<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_providers_endpoint_returns_configured_providers(): void
    {
        // With no OAuth env vars set, both should be false
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
        config(['services.github.client_id' => null, 'services.github.client_secret' => null]);
        config(['modules.oauth' => true]);

        $response = $this->getJson('/api/v1/auth/oauth/providers');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertFalse($data['google']);
        $this->assertFalse($data['github']);
    }

    public function test_providers_endpoint_shows_google_when_configured(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.github.client_id' => null,
            'services.github.client_secret' => null,
            'modules.oauth' => true,
        ]);

        $response = $this->getJson('/api/v1/auth/oauth/providers');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertTrue($data['google']);
        $this->assertFalse($data['github']);
    }

    public function test_providers_endpoint_shows_github_when_configured(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
            'services.github.client_id' => 'test-client-id',
            'services.github.client_secret' => 'test-client-secret',
            'modules.oauth' => true,
        ]);

        $response = $this->getJson('/api/v1/auth/oauth/providers');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertFalse($data['google']);
        $this->assertTrue($data['github']);
    }

    public function test_google_redirect_returns_redirect_when_configured(): void
    {
        config([
            'services.google.client_id' => 'test-google-id',
            'services.google.client_secret' => 'test-google-secret',
            'modules.oauth' => true,
        ]);

        $response = $this->get('/api/v1/auth/oauth/google');

        $response->assertStatus(302);
        $this->assertStringContains('accounts.google.com', $response->headers->get('Location'));
    }

    public function test_github_redirect_returns_redirect_when_configured(): void
    {
        config([
            'services.github.client_id' => 'test-github-id',
            'services.github.client_secret' => 'test-github-secret',
            'modules.oauth' => true,
        ]);

        $response = $this->get('/api/v1/auth/oauth/github');

        $response->assertStatus(302);
        $this->assertStringContains('github.com/login/oauth', $response->headers->get('Location'));
    }

    public function test_google_redirect_404_when_not_configured(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
            'modules.oauth' => true,
        ]);

        $response = $this->get('/api/v1/auth/oauth/google');

        $response->assertStatus(404);
    }

    public function test_github_redirect_404_when_not_configured(): void
    {
        config([
            'services.github.client_id' => null,
            'services.github.client_secret' => null,
            'modules.oauth' => true,
        ]);

        $response = $this->get('/api/v1/auth/oauth/github');

        $response->assertStatus(404);
    }

    public function test_google_callback_with_error_param_aborts(): void
    {
        config([
            'services.google.client_id' => 'test-id',
            'services.google.client_secret' => 'test-secret',
            'modules.oauth' => true,
        ]);

        $response = $this->get('/api/v1/auth/oauth/google/callback?error=access_denied&error_description=User+denied');

        $response->assertStatus(400);
    }

    public function test_github_callback_with_error_param_aborts(): void
    {
        config([
            'services.github.client_id' => 'test-id',
            'services.github.client_secret' => 'test-secret',
            'modules.oauth' => true,
        ]);

        $response = $this->get('/api/v1/auth/oauth/github/callback?error=access_denied');

        $response->assertStatus(400);
    }

    /**
     * Helper: assertStringContains for URL checks.
     */
    private function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack, "Expected non-null string containing '{$needle}'");
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Expected '{$haystack}' to contain '{$needle}'"
        );
    }
}
