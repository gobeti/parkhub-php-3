<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Models\Absence;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CreditTransaction;
use App\Models\Favorite;
use App\Models\Notification;
use App\Models\PushSubscription;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Owns the heavy-lifting user-account flows extracted from
 * UserController (T-1742, pass 4):
 *
 *  - GDPR Art. 17 account anonymization (keeps booking records for
 *    German tax-law retention while stripping all PII)
 *  - GDPR Art. 20 personal-data export assembly
 *  - iCal calendar feed assembly for bookings
 *
 * Pure extraction — the anonymization ordering (mutate bookings →
 * delete personal tables → anonymize user → invalidate tokens), the
 * `[GELÖSCHT]` / `[Gelöschter Nutzer]` placeholders, the guest-booking
 * and audit-log scrubbing, the JSON export field projection and the
 * RFC 5545 iCal line format all match the previous inline controller
 * implementation. Controllers stay responsible for HTTP shaping.
 */
final class UserAccountService
{
    /**
     * Anonymize a user account end-to-end. Returns true on success and
     * false when the supplied password does not match — controllers
     * should translate false into a 403 response.
     */
    public function anonymize(User $user, string $password, string $reason, ?string $ipAddress): bool
    {
        if (! Hash::check($password, $user->password)) {
            return false;
        }

        $anonymousId = 'deleted-'.substr($user->id, 0, 8);

        $this->scrubBookings($user->id);
        $this->deletePersonalRecords($user->id);
        $this->scrubAuditLog($user->id);
        $this->scrubGuestBookings($user->id);

        AuditLog::log([
            'user_id' => $user->id,
            'username' => $user->username,
            'action' => 'gdpr_erasure',
            'details' => ['reason' => $reason],
            'ip_address' => $ipAddress,
        ]);

        $this->anonymizeUserRecord($user, $anonymousId);

        // Caller is responsible for invalidating tokens AFTER the
        // response is built so the session survives long enough to
        // serialize the body. We return true so the controller can
        // order that step correctly.
        return true;
    }

    /**
     * Build the GDPR Art. 20 personal-data export payload.
     *
     * @return array<string, mixed>
     */
    public function exportData(User $user): array
    {
        return [
            'exported_at' => now()->toISOString(),
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'department' => $user->department,
                'created_at' => $user->created_at?->toISOString(),
            ],
            'bookings' => Booking::where('user_id', $user->id)
                ->orderBy('start_time', 'desc')
                ->get(['id', 'lot_name', 'slot_number', 'vehicle_plate', 'start_time', 'end_time', 'status', 'booking_type']),
            'absences' => Absence::where('user_id', $user->id)
                ->orderBy('start_date', 'desc')
                ->get(['id', 'absence_type', 'start_date', 'end_date', 'note']),
            'vehicles' => Vehicle::where('user_id', $user->id)
                ->get(['id', 'plate', 'make', 'model', 'color', 'is_default']),
            'credit_transactions' => CreditTransaction::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get(['id', 'type', 'amount', 'balance_after', 'description', 'created_at']),
            'preferences' => $user->preferences ?? [],
        ];
    }

    /**
     * Render the user's active/confirmed bookings as an RFC 5545 iCal
     * feed. Lines are CRLF-joined with a trailing CRLF, matching the
     * on-wire shape expected by Outlook / Apple Calendar / GCal.
     */
    public function buildIcalFeed(User $user): string
    {
        $bookings = Booking::where('user_id', $user->id)
            ->whereIn('status', ['active', 'confirmed'])
            ->whereNotNull('start_time')
            ->get();

        $orgName = Setting::get('company_name', 'ParkHub');
        $prodId = '-//ParkHub//Calendar//EN';
        $now = gmdate('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            "PRODID:{$prodId}",
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            "X-WR-CALNAME:{$orgName} Parking",
        ];

        foreach ($bookings as $b) {
            $uid = $b->id.'@parkhub';
            // start_time / end_time arrive as Carbon via the datetime cast;
            // `->timestamp` gives the Unix int strict_types wants.
            $start = gmdate('Ymd\THis\Z', $b->start_time->timestamp);
            $end = $b->end_time
                ? gmdate('Ymd\THis\Z', $b->end_time->timestamp)
                : gmdate('Ymd\THis\Z', $b->start_time->timestamp + 3600);

            $summary = "Parking: {$b->slot_number} ({$b->lot_name})";
            $location = $b->lot_name;

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:{$uid}";
            $lines[] = "DTSTAMP:{$now}";
            $lines[] = "DTSTART:{$start}";
            $lines[] = "DTEND:{$end}";
            $lines[] = "SUMMARY:{$summary}";
            $lines[] = "LOCATION:{$location}";
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    /** DE retention: booking rows kept with PII stripped. */
    private function scrubBookings(string $userId): void
    {
        Booking::where('user_id', $userId)->update([
            'vehicle_plate' => '[GELÖSCHT]',
            'notes' => null,
        ]);
    }

    /** Vehicle photos leave disk BEFORE the row is deleted — no orphan files. */
    private function deletePersonalRecords(string $userId): void
    {
        Absence::where('user_id', $userId)->delete();

        $vehicles = Vehicle::where('user_id', $userId)->get();
        foreach ($vehicles as $vehicle) {
            if (! empty($vehicle->photo_path)) {
                Storage::delete($vehicle->photo_path);
            }
        }
        Vehicle::where('user_id', $userId)->delete();

        Favorite::where('user_id', $userId)->delete();
        Notification::where('user_id', $userId)->delete();
        PushSubscription::where('user_id', $userId)->delete();
    }

    private function scrubAuditLog(string $userId): void
    {
        DB::table('audit_log')->where('user_id', $userId)->update([
            'username' => 'deleted-user',
            'ip_address' => '0.0.0.0',
            'details' => null,
        ]);
    }

    private function scrubGuestBookings(string $userId): void
    {
        DB::table('guest_bookings')->where('created_by', $userId)->update([
            'guest_name' => 'Anonymous',
        ]);
    }

    /** Soft-anonymize: user_id FK is non-nullable, so we keep the row. */
    private function anonymizeUserRecord(User $user, string $anonymousId): void
    {
        $user->preferences = null;
        $user->update([
            'name' => '[Gelöschter Nutzer]',
            'email' => $anonymousId.'@deleted.invalid',
            'username' => $anonymousId,
            'password' => Str::random(64),
            'phone' => null,
            'picture' => null,
            'department' => null,
            'preferences' => null,
            'is_active' => false,
        ]);
    }
}
