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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $perPage = min((int) request('per_page', 20), 100);
        $users = User::paginate($perPage);

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
        $data = $request->only(['name', 'email', 'is_active', 'department']);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }
        $user->update($data);
        // Handle role separately — it's excluded from $fillable to prevent mass-assignment escalation
        $roleChanged = false;
        if ($request->filled('role') && $user->role !== $request->role) {
            $user->role = $request->role;
            $user->save();
            $roleChanged = true;
        }

        // Privilege change on the target user: invalidate their existing
        // tokens so the new role can't be bypassed by a stale session, and
        // rotate the acting admin's session ID for defense-in-depth.
        if ($roleChanged || $request->filled('password')) {
            $user->tokens()->delete();
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }
        }

        // Return via toArray() to respect $hidden
        return UserResource::make($user->fresh());
    }

    public function deleteUser(Request $request, string $id): JsonResponse
    {

        if ($id === $request->user()->id) {
            return response()->json(['error' => 'Cannot delete your own account'], 400);
        }
        User::findOrFail($id)->delete();

        return response()->json(['message' => 'User deleted']);
    }

    public function importUsers(ImportUsersRequest $request): JsonResponse
    {

        $imported = 0;
        $usersCollection = collect($request->users);

        // Batch-check existing usernames + emails in 2 queries instead of N queries (closes #59)
        $existingUsernames = User::whereIn('username', $usersCollection->pluck('username'))->pluck('username');
        $existingEmails = User::whereIn('email', $usersCollection->pluck('email'))->pluck('email');

        $toImport = $usersCollection->reject(fn ($u) => $existingUsernames->contains($u['username']) || $existingEmails->contains($u['email'])
        );

        foreach ($toImport as $userData) {
            $user = User::create([
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password'] ?? Str::random(16)),
                'name' => $userData['name'] ?? $userData['username'],
                'is_active' => true,
                'department' => $userData['department'] ?? null,
                'preferences' => ['language' => 'en', 'theme' => 'system'],
            ]);
            $user->role = $userData['role'] ?? 'user';
            $user->save();
            $imported++;
        }

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
        $action = $request->action;
        $userIds = $request->user_ids;
        $currentUserId = $request->user()->id;
        $results = [];

        foreach ($userIds as $userId) {
            if ($userId === $currentUserId && in_array($action, ['deactivate', 'delete'])) {
                $results[] = ['user_id' => $userId, 'status' => 'skipped', 'reason' => 'Cannot perform this action on yourself'];

                continue;
            }

            $user = User::find($userId);
            if (! $user) {
                $results[] = ['user_id' => $userId, 'status' => 'failed', 'reason' => 'User not found'];

                continue;
            }

            match ($action) {
                'activate' => $user->update(['is_active' => true]),
                'deactivate' => $user->update(['is_active' => false]),
                'change_role' => (function () use ($user, $request) {
                    $user->role = $request->role;
                    $user->save();
                    // Privilege change — kill existing sessions so the new
                    // role applies immediately on next login.
                    $user->tokens()->delete();
                })(),
                'delete' => $user->delete(),
            };

            $results[] = ['user_id' => $userId, 'status' => 'success'];
        }

        AuditLog::log([
            'user_id' => $currentUserId,
            'username' => $request->user()->username,
            'action' => 'admin_bulk_'.$action,
            'details' => ['count' => count($userIds)],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'action' => $action,
            'results' => $results,
            'total' => count($results),
            'successful' => count(array_filter($results, fn ($r) => $r['status'] === 'success')),
        ]);
    }
}
