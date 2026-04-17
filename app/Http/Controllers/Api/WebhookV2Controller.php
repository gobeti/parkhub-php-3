<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ValidatesExternalUrls;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebhookV2Request;
use App\Http\Requests\UpdateWebhookV2Request;
use App\Services\Webhook\WebhookDispatchService;
use Illuminate\Http\JsonResponse;

/**
 * Webhooks v2 controller — CRUD, test, and delivery log endpoints.
 *
 * Supports HMAC-SHA256 signing and retry logic for webhook deliveries.
 *
 * Admin endpoints:
 *   GET    /api/v1/admin/webhooks-v2                 — list all webhooks
 *   POST   /api/v1/admin/webhooks-v2                 — create webhook
 *   GET    /api/v1/admin/webhooks-v2/{id}            — get single webhook
 *   PUT    /api/v1/admin/webhooks-v2/{id}            — update webhook
 *   DELETE /api/v1/admin/webhooks-v2/{id}            — delete webhook
 *   POST   /api/v1/admin/webhooks-v2/{id}/test       — send test event
 *   GET    /api/v1/admin/webhooks-v2/{id}/deliveries — delivery history
 */
class WebhookV2Controller extends Controller
{
    use ValidatesExternalUrls;

    public function __construct(private readonly WebhookDispatchService $dispatcher) {}

    /**
     * List all v2 webhooks.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->dispatcher->list(),
        ]);
    }

    /**
     * Get a single webhook by ID.
     */
    public function show(string $id): JsonResponse
    {
        $webhook = $this->dispatcher->find($id);

        if (! $webhook) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'data' => $webhook,
        ]);
    }

    /**
     * Create a new v2 webhook.
     */
    public function store(StoreWebhookV2Request $request): JsonResponse
    {
        $url = (string) $request->input('url');
        if (! $this->isExternalUrl($url)) {
            return $this->invalidUrl();
        }

        $webhook = $this->dispatcher->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => $webhook,
        ], 201);
    }

    /**
     * Update an existing v2 webhook.
     */
    public function update(UpdateWebhookV2Request $request, string $id): JsonResponse
    {
        $existing = $this->dispatcher->find($id);

        if (! $existing) {
            return $this->notFound();
        }

        $newUrl = (string) $request->input('url', $existing['url']);
        if ($newUrl !== $existing['url'] && ! $this->isExternalUrl($newUrl)) {
            return $this->invalidUrl();
        }

        $webhook = $this->dispatcher->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'data' => $webhook,
        ]);
    }

    /**
     * Delete a v2 webhook.
     */
    public function destroy(string $id): JsonResponse
    {
        if (! $this->dispatcher->delete($id)) {
            return $this->notFound();
        }

        return response()->json(['success' => true, 'message' => 'Webhook deleted']);
    }

    /**
     * Send a test event to a webhook endpoint.
     */
    public function test(string $id): JsonResponse
    {
        $webhook = $this->dispatcher->find($id);

        if (! $webhook) {
            return $this->notFound();
        }

        $result = $this->dispatcher->dispatch(
            $webhook,
            'test.ping',
            ['message' => 'This is a test event from ParkHub'],
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Get delivery history for a webhook.
     */
    public function deliveries(string $id): JsonResponse
    {
        $webhook = $this->dispatcher->find($id);

        if (! $webhook) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'data' => $this->dispatcher->deliveries($id),
        ]);
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['message' => 'Webhook not found'],
        ], 404);
    }

    private function invalidUrl(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['message' => 'URL must be a valid external (non-private) HTTP(S) address'],
        ], 422);
    }
}
