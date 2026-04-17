<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUserActionRequest;
use App\Http\Requests\ImportUsersRequest;
use App\Http\Requests\UpdateParkingSlotRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\ParkingSlotResource;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\GuestBooking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Services\Admin\AdminUserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(private readonly AdminUserManagementService $users) {}

    public function users(Request $request): JsonResponse
    {
        $perPage = min((int) request('per_page', 20), 100);
        $users = $this->users->listUsers($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'error' => null,
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function updateUser(UpdateUserRequest $request, string $id)
    {
        $user = User::findOrFail($id);
        $result = $this->users->updateUser($user, $request->validated());

        // Rotate the acting admin's session when the target's privileges
        // changed — defense-in-depth to complement the token invalidation
        // that already happened inside the service.
        if (($result['role_changed'] || $result['password_changed']) && $request->hasSession()) {
            $request->session()->regenerate();
        }

        return UserResource::make($result['user']);
    }

    public function deleteUser(Request $request, string $id): JsonResponse
    {
        $target = User::findOrFail($id);

        if (! $this->users->deleteUser($target, $request->user())) {
            return response()->json(['error' => 'Cannot delete your own account'], 400);
        }

        return response()->json(['message' => 'User deleted']);
    }

    public function importUsers(ImportUsersRequest $request): JsonResponse
    {
        $imported = $this->users->importUsers($request->validated()['users']);

        return response()->json(['imported' => $imported]);
    }

    public function bookings(Request $request): JsonResponse
    {

        $query = Booking::with('user')->orderBy('start_time', 'desc');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->has('lot_name') && $request->lot_name !== 'all') {
            $query->where('lot_name', $request->lot_name);
        }
        if ($request->has('from_date')) {
            $query->where('start_time', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('end_time', '<=', $request->to_date.' 23:59:59');
        }

        $perPage = min((int) $request->get('per_page', 100), 500);

        return response()->json($query->paginate($perPage));
    }

    public function cancelBooking(Request $request, string $id)
    {

        $booking = Booking::findOrFail($id);
        $booking->update(['status' => Booking::STATUS_CANCELLED]);

        AuditLog::log([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'admin_booking_cancelled',
            'details' => ['booking_id' => $id],
        ]);

        return BookingResource::make($booking->fresh());
    }

    // ── Guest Bookings ────────────────────────────────────────────────────────

    public function guestBookings(Request $request): JsonResponse
    {

        $query = GuestBooking::with(['lot', 'slot', 'creator'])
            ->orderBy('start_time', 'desc');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->has('from_date')) {
            $query->where('start_time', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('end_time', '<=', $request->to_date.' 23:59:59');
        }

        $perPage = min((int) $request->get('per_page', 50), 200);
        $paginated = $query->paginate($perPage);

        $guests = collect($paginated->items())->map(function ($g) {
            return [
                'id' => $g->id,
                'guest_name' => $g->guest_name,
                'guest_code' => $g->guest_code,
                'lot_id' => $g->lot_id,
                'lot_name' => $g->lot?->name ?? '-',
                'slot_id' => $g->slot_id,
                'slot_number' => $g->slot?->number ?? '-',
                'start_time' => $g->start_time,
                'end_time' => $g->end_time,
                'vehicle_plate' => $g->vehicle_plate,
                'status' => $g->status,
                'created_by' => $g->created_by,
                'created_by_name' => $g->creator?->name ?? '-',
                'created_at' => $g->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $guests,
            'error' => null,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    public function cancelGuestBooking(Request $request, string $id): JsonResponse
    {

        $guest = GuestBooking::findOrFail($id);
        $guest->update(['status' => 'cancelled']);

        // Also cancel the associated regular booking
        Booking::where('lot_id', $guest->lot_id)
            ->where('slot_id', $guest->slot_id)
            ->where('start_time', $guest->start_time)
            ->where('end_time', $guest->end_time)
            ->whereIn('status', ['confirmed', 'active'])
            ->update(['status' => Booking::STATUS_CANCELLED]);

        AuditLog::log([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'admin_guest_booking_cancelled',
            'details' => ['guest_booking_id' => $id, 'guest_name' => $guest->guest_name],
        ]);

        return response()->json([
            'success' => true,
            'data' => $guest->fresh(),
            'error' => null,
        ]);
    }

    public function auditLog(Request $request): JsonResponse
    {

        $query = AuditLog::orderBy('created_at', 'desc');

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }
        if ($request->has('search')) {
            $search = addcslashes($request->search, '%_\\');
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', '%'.$search.'%')
                    ->orWhere('action', 'like', '%'.$search.'%');
            });
        }

        return response()->json($query->paginate($request->get('per_page', 50)));
    }

    public function updateSlot(UpdateParkingSlotRequest $request, string $id)
    {
        $slot = ParkingSlot::findOrFail($id);
        $slot->update($request->only(['slot_number', 'status', 'reserved_for_department', 'zone_id']));

        return ParkingSlotResource::make($slot->fresh());
    }

    public function deleteLot(Request $request, string $id): JsonResponse
    {

        $lot = ParkingLot::findOrFail($id);
        $lot->delete();

        return response()->json(['message' => 'Lot deleted']);
    }

    /**
     * Bulk admin operations on users.
     */
    public function bulkAction(BulkUserActionRequest $request): JsonResponse
    {
        $summary = $this->users->bulkAction(
            action: (string) $request->action,
            userIds: (array) $request->user_ids,
            actor: $request->user(),
            role: $request->filled('role') ? (string) $request->role : null,
            ip: $request->ip(),
        );

        return response()->json($summary);
    }
}
