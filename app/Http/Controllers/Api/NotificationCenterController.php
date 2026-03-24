<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationCenterController extends Controller
{
    private const NOTIFICATION_TYPES = [
        'booking_confirmed' => ['icon' => 'check-circle', 'severity' => 'success', 'label' => 'Booking Confirmed'],
        'booking_cancelled' => ['icon' => 'x-circle', 'severity' => 'warning', 'label' => 'Booking Cancelled'],
        'booking_reminder' => ['icon' => 'clock', 'severity' => 'info', 'label' => 'Booking Reminder'],
        'waitlist_offer' => ['icon' => 'queue', 'severity' => 'info', 'label' => 'Waitlist Offer'],
        'maintenance_alert' => ['icon' => 'wrench', 'severity' => 'warning', 'label' => 'Maintenance Alert'],
        'system_announcement' => ['icon' => 'megaphone', 'severity' => 'neutral', 'label' => 'System Announcement'],
        'payment_received' => ['icon' => 'currency-dollar', 'severity' => 'success', 'label' => 'Payment Received'],
        'visitor_arrived' => ['icon' => 'user-plus', 'severity' => 'info', 'label' => 'Visitor Arrived'],
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $filter = $request->query('filter', 'all');
        $typeFilter = $request->query('notification_type');

        $query = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($filter === 'read') {
            $query->where('read', true);
        } elseif ($filter === 'unread') {
            $query->where('read', false);
        }

        if ($typeFilter) {
            $query->where('type', $typeFilter);
        }

        $total = $query->count();
        $unreadCount = Notification::where('user_id', $user->id)->where('read', false)->count();

        $notifications = $query
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(fn ($n) => $this->enrichNotification($n));

        return response()->json([
            'items' => $notifications,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'unread_count' => $unreadCount,
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->where('read', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = Notification::where('user_id', $request->user()->id)
            ->where('read', false)
            ->update(['read' => true]);

        return response()->json(['updated' => $updated]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $notification) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Notification not found'], 404);
        }

        $notification->delete();

        return response()->json(['deleted' => true]);
    }

    private function enrichNotification(Notification $notification): array
    {
        $type = $this->parseType($notification);
        $meta = self::NOTIFICATION_TYPES[$type] ?? self::NOTIFICATION_TYPES['system_announcement'];

        return [
            'id' => $notification->id,
            'notification_type' => $type,
            'title' => $notification->title,
            'message' => $notification->message,
            'read' => $notification->read,
            'action_url' => $this->extractActionUrl($type, $notification),
            'icon' => $meta['icon'],
            'severity' => $meta['severity'],
            'type_label' => $meta['label'],
            'created_at' => $notification->created_at->toIso8601String(),
            'date_group' => $this->dateGroup($notification->created_at),
        ];
    }

    private function parseType(Notification $notification): string
    {
        $stored = $notification->type;
        if (isset(self::NOTIFICATION_TYPES[$stored])) {
            return $stored;
        }

        $title = strtolower($notification->title ?? '');
        if (str_contains($title, 'confirmed')) {
            return 'booking_confirmed';
        }
        if (str_contains($title, 'cancelled') || str_contains($title, 'canceled')) {
            return 'booking_cancelled';
        }
        if (str_contains($title, 'reminder')) {
            return 'booking_reminder';
        }
        if (str_contains($title, 'waitlist')) {
            return 'waitlist_offer';
        }
        if (str_contains($title, 'maintenance')) {
            return 'maintenance_alert';
        }
        if (str_contains($title, 'payment')) {
            return 'payment_received';
        }
        if (str_contains($title, 'visitor')) {
            return 'visitor_arrived';
        }

        return 'system_announcement';
    }

    private function extractActionUrl(string $type, Notification $notification): ?string
    {
        $data = $notification->data;
        $bookingId = $data['booking_id'] ?? null;

        return match ($type) {
            'booking_confirmed', 'booking_reminder', 'booking_cancelled' => $bookingId ? "/bookings/{$bookingId}" : '/bookings',
            'waitlist_offer' => '/waitlist',
            'payment_received' => '/payments',
            'visitor_arrived' => '/visitors',
            'maintenance_alert' => '/admin/maintenance',
            default => null,
        };
    }

    private function dateGroup(Carbon $date): string
    {
        if ($date->isToday()) {
            return 'today';
        }
        if ($date->isYesterday()) {
            return 'yesterday';
        }

        return $date->format('Y-m-d');
    }
}
