<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PluginController extends Controller
{
    /**
     * Built-in plugin registry with event hooks.
     */
    private static function registry(): array
    {
        return [
            [
                'id' => 'slack-notifier',
                'name' => 'Slack Notifier',
                'description' => 'Send booking and lot notifications to a Slack channel',
                'version' => '1.0.0',
                'author' => 'ParkHub',
                'enabled' => (bool) config('parkhub.plugins.slack_notifier.enabled', false),
                'hooks' => ['booking_created', 'booking_cancelled', 'lot_full'],
                'config' => [
                    'webhook_url' => config('parkhub.plugins.slack_notifier.webhook_url', ''),
                    'channel' => config('parkhub.plugins.slack_notifier.channel', '#parking'),
                    'notify_on' => config('parkhub.plugins.slack_notifier.notify_on', ['booking_created', 'booking_cancelled', 'lot_full']),
                ],
            ],
            [
                'id' => 'auto-assign-preferred',
                'name' => 'Auto-Assign Preferred Spot',
                'description' => 'Automatically assign users to their preferred/favorite parking spot when booking',
                'version' => '1.0.0',
                'author' => 'ParkHub',
                'enabled' => (bool) config('parkhub.plugins.auto_assign.enabled', false),
                'hooks' => ['booking_created', 'user_registered'],
                'config' => [
                    'fallback_to_any' => config('parkhub.plugins.auto_assign.fallback_to_any', true),
                    'priority' => config('parkhub.plugins.auto_assign.priority', 'favorites_first'),
                ],
            ],
        ];
    }

    /**
     * GET /admin/plugins — list all plugins with status.
     */
    public function index(): JsonResponse
    {
        $plugins = self::registry();

        return response()->json([
            'success' => true,
            'data' => [
                'plugins' => $plugins,
                'total' => count($plugins),
                'enabled' => count(array_filter($plugins, fn ($p) => $p['enabled'])),
                'available_hooks' => ['booking_created', 'booking_cancelled', 'user_registered', 'lot_full'],
            ],
        ]);
    }

    /**
     * PUT /admin/plugins/{id}/toggle — enable or disable a plugin.
     */
    public function toggle(string $id): JsonResponse
    {
        $plugins = self::registry();
        $plugin = collect($plugins)->firstWhere('id', $id);

        if (! $plugin) {
            return response()->json([
                'success' => false,
                'error' => 'PLUGIN_NOT_FOUND',
                'message' => "Plugin '{$id}' not found",
            ], 404);
        }

        // In a real implementation this would persist to DB/cache.
        // For the built-in registry we return the toggled state.
        $newState = ! $plugin['enabled'];

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $id,
                'enabled' => $newState,
                'message' => $newState ? 'Plugin enabled' : 'Plugin disabled',
            ],
        ]);
    }

    /**
     * GET /admin/plugins/{id}/config — get plugin configuration.
     */
    public function getConfig(string $id): JsonResponse
    {
        $plugins = self::registry();
        $plugin = collect($plugins)->firstWhere('id', $id);

        if (! $plugin) {
            return response()->json([
                'success' => false,
                'error' => 'PLUGIN_NOT_FOUND',
                'message' => "Plugin '{$id}' not found",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $id,
                'name' => $plugin['name'],
                'config' => $plugin['config'],
                'hooks' => $plugin['hooks'],
            ],
        ]);
    }

    /**
     * PUT /admin/plugins/{id}/config — update plugin configuration.
     */
    public function updateConfig(Request $request, string $id): JsonResponse
    {
        $plugins = self::registry();
        $plugin = collect($plugins)->firstWhere('id', $id);

        if (! $plugin) {
            return response()->json([
                'success' => false,
                'error' => 'PLUGIN_NOT_FOUND',
                'message' => "Plugin '{$id}' not found",
            ], 404);
        }

        $config = $request->input('config', []);

        if (! is_array($config)) {
            return response()->json([
                'success' => false,
                'error' => 'INVALID_CONFIG',
                'message' => 'Config must be an object',
            ], 422);
        }

        // Merge with existing config
        $merged = array_merge($plugin['config'], $config);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $id,
                'name' => $plugin['name'],
                'config' => $merged,
            ],
        ]);
    }
}
