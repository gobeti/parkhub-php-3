<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_docs_endpoint_returns_success(): void
    {
        // Scramble registers /docs/api route — may return BinaryFileResponse
        $response = $this->get('/docs/api');

        // Scramble returns a view or BinaryFileResponse, should not be 500
        $status = $response->baseResponse->getStatusCode();
        $this->assertTrue(in_array($status, [200, 302, 301]),
            "Expected 200/301/302 but got {$status}");
    }

    public function test_api_docs_json_endpoint_accessible(): void
    {
        // Scramble JSON spec at /docs/api.json
        $response = $this->get('/docs/api.json');

        $status = $response->baseResponse->getStatusCode();
        $this->assertTrue(in_array($status, [200, 302, 301]),
            "Expected 200/301/302 but got {$status}");
    }

    public function test_api_docs_module_is_enabled(): void
    {
        $this->assertTrue(
            (bool) config('modules.api_docs'),
            'MODULE_API_DOCS should be enabled'
        );
    }
}
