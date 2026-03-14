<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebhookJob;
use App\Mail\BookingConfirmation;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingNote;
use App\Models\CreditTransaction;
use App\Models\GuestBooking;
use App\Models\Notification;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\WaitlistEntry;
use App\Models\Webhook;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::where('user_id', $request->user()->id);
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('from_date')) {
            $query->where('start_time', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('end_time', '<=', $request->to_date);
        }

        return response()->json($query->orderBy('start_time', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lot_id' => 'required|uuid',
            'slot_id' => 'nullable|uuid',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time',
            'booking_type' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
            'vehicle_plate' => 'nullable|string|max:20',
            'license_plate' => 'nullable|string|max:20',
        ]);

        $startTime = Carbon::parse($request->start_time);
        if ($startTime->isPast()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'INVALID_BOOKING_TIME', 'message' => 'Booking start time must be in the future.'],
                'meta' => null,
            ], 422);
        }

        // Credits check — if credits system is enabled, verify balance
        $creditsEnabled = Setting::get('credits_enabled', 'false') === 'true';
        $creditsPerBooking = (int) Setting::get('credits_per_booking', '1');
        $user = $request->user();

        if ($creditsEnabled && ! $user->isAdmin()) {
            if ($user->credits_balance < $creditsPerBooking) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'error' => [
                        'code' => 'INSUFFICIENT_CREDITS',
                        'message' => 'Not enough credits. Required: '.$creditsPerBooking.', Available: '.$user->credits_balance,
                    ],
                    'meta' => null,
                ], 422);
            }
        }

        $endTime = $request->end_time
            ? Carbon::parse($request->end_time)->toDateTimeString()
            : now()->addHours(8)->toDateTimeString();
        $startTimeStr = $startTime->toDateTimeString();
        $slotId = $request->slot_id;

        // Auto-assign slot if not provided (outside transaction — read-only scan)
        if (! $slotId) {
            $bookedSlotIds = Booking::where('lot_id', $request->lot_id)
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTimeStr)
                ->pluck('slot_id');
            $slot = ParkingSlot::where('lot_id', $request->lot_id)
                ->whereNotIn('id', $bookedSlotIds)
                ->first();
            if (! $slot) {
                return response()->json(['error' => 'NO_SLOTS_AVAILABLE', 'message' => 'No available slots'], 409);
            }
            $slotId = $slot->id;
        }

        $booking = null;

        try {
            // Use a transaction with exclusive slot lock to prevent race conditions
            DB::transaction(function () use ($request, $slotId, $endTime, $startTimeStr, &$booking, $creditsEnabled, $creditsPerBooking) {
                // Lock the slot row for this transaction
                $slot = ParkingSlot::where('id', $slotId)->lockForUpdate()->firstOrFail();

                // Re-check conflict with locked data
                $conflict = Booking::where('slot_id', $slotId)
                    ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                    ->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTimeStr)
                    ->exists();

                if ($conflict) {
                    throw new \Exception('SLOT_CONFLICT');
                }

                $lot = ParkingLot::findOrFail($request->lot_id);

                $booking = Booking::create([
                    'user_id' => $request->user()->id,
                    'lot_id' => $request->lot_id,
                    'slot_id' => $slotId,
                    'booking_type' => $request->booking_type ?? 'einmalig',
                    'lot_name' => $lot->name,
                    'slot_number' => $slot->slot_number,
                    'vehicle_plate' => $request->license_plate ?? $request->vehicle_plate,
                    'start_time' => $request->start_time,
                    'end_time' => $endTime,
                    'status' => Booking::STATUS_CONFIRMED,
                    'notes' => $request->notes,
                    'recurrence' => $request->recurrence,
                ]);
                // Deduct credits within the same transaction
                if ($creditsEnabled && ! $request->user()->isAdmin()) {
                    $request->user()->decrement('credits_balance', $creditsPerBooking);
                    CreditTransaction::create([
                        'user_id' => $request->user()->id,
                        'booking_id' => $booking->id,
                        'amount' => -$creditsPerBooking,
                        'type' => 'deduction',
                        'description' => 'Booking #'.substr($booking->id, 0, 8),
                    ]);
                }
            }, 3); // 3 retries on deadlock
        } catch (\Exception $e) {
            if ($e->getMessage() === 'SLOT_CONFLICT') {
                return response()->json(['error' => 'SLOT_UNAVAILABLE', 'message' => 'Slot is already booked'], 409);
            }
            throw $e;
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'booking_created',
            'details' => ['booking_id' => $booking->id, 'slot' => $booking->slot_number],
        ]);

        // Send booking confirmation email (queued — non-blocking)
        $recipient = $request->user();
        if ($recipient->email) {
            Mail::to($recipient->email)->queue(new BookingConfirmation($booking, $recipient));
        }

        // Dispatch webhook events
        foreach (Webhook::where('active', true)->get() as $webhook) {
            if (in_array('booking.created', $webhook->events ?? [])) {
                SendWebhookJob::dispatch($webhook->id, 'booking.created', [
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                    'slot_id' => $booking->slot_id,
                    'start_time' => $booking->start_time,
                    'end_time' => $booking->end_time,
                ]);
            }
        }

        return response()->json($booking, 201);
    }

    public function show(Request $request, string $id)
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['success' => false, 'data' => null, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Booking not found.'], 'meta' => null], 404);
        }

        if ($booking->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'data' => null, 'error' => ['code' => 'FORBIDDEN', 'message' => 'You do not have access to this booking.'], 'meta' => null], 403);
        }

        return response()->json($booking);
    }

    public function destroy(Request $request, string $id)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($id);

        // Mark as cancelled instead of hard-deleting — preserves audit trail
        $booking->update(['status' => Booking::STATUS_CANCELLED]);

        // Refund credits if credits system is enabled
        $creditsEnabled = Setting::get('credits_enabled', 'false') === 'true';
        $creditsPerBooking = (int) Setting::get('credits_per_booking', '1');
        if ($creditsEnabled && ! $request->user()->isAdmin()) {
            $request->user()->increment('credits_balance', $creditsPerBooking);
            CreditTransaction::create([
                'user_id' => $request->user()->id,
                'booking_id' => $booking->id,
                'amount' => $creditsPerBooking,
                'type' => 'refund',
                'description' => 'Cancelled booking #'.substr($booking->id, 0, 8),
            ]);
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'booking_cancelled',
            'details' => ['booking_id' => $id, 'lot_id' => $booking->lot_id, 'slot_id' => $booking->slot_id],
        ]);

        // Notify waitlist users that a slot has become available in this lot
        $this->notifyWaitlist($booking->lot_id, $booking->slot_id);

        // Dispatch webhook events
        foreach (Webhook::where('active', true)->get() as $webhook) {
            if (in_array('booking.cancelled', $webhook->events ?? [])) {
                SendWebhookJob::dispatch($webhook->id, 'booking.cancelled', [
                    'booking_id' => $booking->id,
                ]);
            }
        }

        return response()->json(['message' => 'Booking cancelled']);
    }

    private function notifyWaitlist(string $lotId, string $slotId): void
    {
        $waiting = WaitlistEntry::where('lot_id', $lotId)
            ->whereNull('notified_at')
            ->with('user')
            ->orderBy('created_at')
            ->limit(3)
            ->get();

        foreach ($waiting as $entry) {
            if ($entry->user && $entry->user->email) {
                $entry->update(['notified_at' => now()]);
                Notification::create([
                    'user_id' => $entry->user_id,
                    'type' => 'waitlist_slot_available',
                    'title' => 'Stellplatz verfügbar',
                    'message' => 'Ein Stellplatz in Ihrem gewünschten Parkplatz ist jetzt verfügbar. Jetzt buchen!',
                    'data' => ['lot_id' => $lotId, 'slot_id' => $slotId],
                ]);
            }
        }
    }

    public function quickBook(Request $request)
    {
        // Accepts either slot_id directly, or lot_id+date to auto-pick
        if ($request->has('slot_id')) {
            $slot = ParkingSlot::findOrFail($request->slot_id);
        } elseif ($request->has('lot_id')) {
            $date = $request->date ? now()->parse($request->date) : now();
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();
            $taken = Booking::where('lot_id', $request->lot_id)
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                ->where('start_time', '<', $dayEnd)
                ->where('end_time', '>', $dayStart)
                ->pluck('slot_id');
            $slot = ParkingSlot::where('lot_id', $request->lot_id)
                ->where('status', 'available')
                ->whereNotIn('id', $taken)
                ->first();
            if (! $slot) {
                return response()->json(['error' => 'NO_SLOTS', 'message' => 'No slots available'], 409);
            }
        } else {
            return response()->json(['error' => 'INVALID_REQUEST', 'message' => 'Provide slot_id or lot_id'], 422);
        }

        $startTime = $request->date ? now()->parse($request->date)->startOfDay() : now();
        $endOfDay = $request->date ? now()->parse($request->date)->endOfDay() : now()->endOfDay();

        $booking = null;

        try {
            DB::transaction(function () use ($request, $slot, $startTime, $endOfDay, &$booking) {
                // Lock the slot row to prevent race conditions
                ParkingSlot::where('id', $slot->id)->lockForUpdate()->firstOrFail();

                $conflict = Booking::where('slot_id', $slot->id)
                    ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                    ->where('start_time', '<', $endOfDay)
                    ->where('end_time', '>', $startTime)
                    ->exists();

                if ($conflict) {
                    throw new \Exception('SLOT_CONFLICT');
                }

                $booking = Booking::create([
                    'user_id' => $request->user()->id,
                    'lot_id' => $slot->lot_id,
                    'slot_id' => $slot->id,
                    'booking_type' => 'einmalig',
                    'lot_name' => $slot->lot?->name,
                    'slot_number' => $slot->slot_number,
                    'vehicle_plate' => $request->license_plate,
                    'start_time' => now(),
                    'end_time' => $endOfDay,
                    'status' => Booking::STATUS_CONFIRMED,
                ]);
            }, 3);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'SLOT_CONFLICT') {
                return response()->json(['error' => 'SLOT_UNAVAILABLE', 'message' => 'Slot taken'], 409);
            }
            throw $e;
        }

        return response()->json($booking, 200);
    }

    public function guestBooking(Request $request)
    {
        $request->validate([
            'lot_id' => 'required|uuid',
            'slot_id' => 'nullable|uuid',
            'guest_name' => 'required|string',
            'end_time' => 'required|date',
        ]);

        $slotId = $request->slot_id;
        if (! $slotId) {
            $startTime = $request->start_time ?? now();
            $bookedSlotIds = Booking::where('lot_id', $request->lot_id)
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                ->where('start_time', '<', $request->end_time)
                ->where('end_time', '>', $startTime)
                ->pluck('slot_id');
            $slot = ParkingSlot::where('lot_id', $request->lot_id)
                ->whereNotIn('id', $bookedSlotIds)
                ->first();
            if (! $slot) {
                return response()->json(['error' => 'NO_SLOTS_AVAILABLE'], 409);
            }
            $slotId = $slot->id;
        }

        $guest = null;

        try {
            DB::transaction(function () use ($request, $slotId, &$guest) {
                // Lock the slot row to prevent race conditions
                ParkingSlot::where('id', $slotId)->lockForUpdate()->firstOrFail();

                $guestStartTime = $request->start_time ?? now();

                // Re-check conflict with locked data
                $conflict = Booking::where('slot_id', $slotId)
                    ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                    ->where('start_time', '<', $request->end_time)
                    ->where('end_time', '>', $guestStartTime)
                    ->exists();

                if ($conflict) {
                    throw new \Exception('SLOT_CONFLICT');
                }

                $guest = GuestBooking::create([
                    'created_by' => $request->user()->id,
                    'lot_id' => $request->lot_id,
                    'slot_id' => $slotId,
                    'guest_name' => $request->guest_name,
                    'guest_code' => strtoupper(Str::random(8)),
                    'start_time' => $guestStartTime,
                    'end_time' => $request->end_time,
                    'vehicle_plate' => $request->vehicle_plate,
                    'status' => Booking::STATUS_CONFIRMED,
                ]);

                Booking::create([
                    'user_id' => $request->user()->id,
                    'lot_id' => $request->lot_id,
                    'slot_id' => $slotId,
                    'booking_type' => 'einmalig',
                    'vehicle_plate' => $request->vehicle_plate,
                    'start_time' => $guestStartTime,
                    'end_time' => $request->end_time,
                    'status' => Booking::STATUS_CONFIRMED,
                    'notes' => 'Guest: '.$request->guest_name,
                ]);
            }, 3);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'SLOT_CONFLICT') {
                return response()->json(['error' => 'SLOT_UNAVAILABLE', 'message' => 'Slot is already booked'], 409);
            }
            throw $e;
        }

        return response()->json($guest, 201);
    }

    public function swap(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|uuid',
            'target_slot_id' => 'required|uuid',
        ]);

        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($request->booking_id);

        $newSlot = ParkingSlot::findOrFail($request->target_slot_id);

        // Validate that the target slot belongs to the same lot as the booking
        if ($newSlot->lot_id !== $booking->lot_id) {
            return response()->json(['error' => 'CROSS_LOT_SWAP', 'message' => 'Target slot must belong to the same lot as the current booking'], 422);
        }

        $conflict = Booking::where('slot_id', $request->target_slot_id)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
            ->where('start_time', '<', $booking->end_time)
            ->where('end_time', '>', $booking->start_time)
            ->exists();

        if ($conflict) {
            return response()->json(['error' => 'SLOT_UNAVAILABLE'], 409);
        }
        $booking->update([
            'slot_id' => $request->target_slot_id,
            'slot_number' => $newSlot->slot_number,
        ]);

        return response()->json($booking->fresh());
    }

    public function updateNotes(Request $request, string $id)
    {
        // Enforce ownership — regular users may only update their own bookings.
        // Admins may update any booking's notes.
        $user = $request->user();
        if ($user->isAdmin()) {
            $booking = Booking::findOrFail($id);
        } else {
            $booking = Booking::where('user_id', $user->id)->findOrFail($id);
        }

        $request->validate([
            'notes' => 'nullable|string|max:2000',
            'note' => 'nullable|string|max:2000',
        ]);

        $booking->update(['notes' => $request->notes]);

        if ($request->filled('note')) {
            BookingNote::create([
                'booking_id' => $id,
                'user_id' => $user->id,
                'note' => $request->note,
            ]);
        }

        return response()->json($booking->fresh());
    }

    public function checkin(Request $request, string $id)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($id);
        $booking->update(['checked_in_at' => now(), 'status' => Booking::STATUS_ACTIVE]);
        AuditLog::create([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'booking_checkin',
            'details' => ['booking_id' => $id],
        ]);

        return response()->json($booking->fresh());
    }

    public function update(Request $request, string $id)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($id);
        // Only allow notes and vehicle_plate updates via this endpoint.
        // Status changes must go through specific endpoints (cancel, checkin, etc.)
        $data = $request->only(['notes', 'vehicle_plate']);
        $booking->update($data);

        return response()->json($booking->fresh());
    }

    public function calendarEvents(Request $request)
    {
        $from = $request->from ?? now()->startOfMonth()->toDateTimeString();
        $to = $request->to ?? now()->endOfMonth()->toDateTimeString();
        $bookings = Booking::where('user_id', $request->user()->id)
            ->where('start_time', '>=', $from)
            ->where('end_time', '<=', $to)
            ->get();
        $events = $bookings->map(function ($b) {
            return [
                'id' => $b->id,
                'title' => $b->lot_name.' — '.$b->slot_number,
                'start' => $b->start_time,
                'end' => $b->end_time,
                'type' => 'booking',
                'status' => $b->status,
            ];
        });

        return response()->json($events->values());
    }

    public function createSwapRequest(Request $request, string $id)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($id);
        $target = Booking::findOrFail($request->target_booking_id);

        return response()->json([
            'id' => Str::uuid(),
            'booking_id' => $booking->id,
            'target_booking_id' => $target->id,
            'status' => 'pending',
            'created_at' => now()->toISOString(),
        ], 201);
    }

    public function respondSwapRequest(Request $request, string $id)
    {
        // Simplified swap implementation
        $accept = $request->input('accept', false);

        return response()->json(['id' => $id, 'status' => $accept ? 'accepted' : 'declined']);
    }
}
