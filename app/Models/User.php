<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    protected $fillable = [
        'username',
        'email',
        'password',
        'name',
        'picture',
        'phone',
        'role',
        'preferences',
        'is_active',
        'department',
        'last_login',
        'monthly_credit_limit',
        'monthly_credits_used',
        'credits_reset_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'preferences'          => 'array',
            'is_active'            => 'boolean',
            'last_login'           => 'datetime',
            'email_verified_at'    => 'datetime',
            'credits_reset_at'     => 'datetime',
            'monthly_credit_limit' => 'integer',
            'monthly_credits_used' => 'integer',
        ];
    }

    // ────────────────────────────────────────────────
    //   Relationships
    // ────────────────────────────────────────────────
    public function bookings()           { return $this->hasMany(Booking::class); }
    public function vehicles()           { return $this->hasMany(Vehicle::class); }
    public function absences()           { return $this->hasMany(Absence::class); }
    public function notifications_list() { return $this->hasMany(Notification::class); }
    public function favorites()          { return $this->hasMany(Favorite::class); }
    public function recurringBookings()  { return $this->hasMany(RecurringBooking::class); }

    // ────────────────────────────────────────────────
    //   Role helper
    // ────────────────────────────────────────────────
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'superadmin']);
    }

    // ────────────────────────────────────────────────
    //   Monthly Credit System (always live — no cache)
    // ────────────────────────────────────────────────

    /**
     * Check if the user can book the requested hours.
     * Always queries the bookings table directly — no cached counter.
     */
    public function canBookHours(float|int $hours, $bookingStart = null): bool
    {
        if ($this->monthly_credit_limit <= 0) {
            return true; // no limit configured
        }

        $bookingStart = $bookingStart ? Carbon::parse($bookingStart) : Carbon::now();
        $used = $this->bookedHoursForMonth($bookingStart->year, $bookingStart->month);

        return ($used + $hours) <= $this->monthly_credit_limit;
    }

    /**
     * No-op: credits are now tracked purely via the bookings table.
     * Kept for API compatibility with BookingController.
     */
    public function useCredits(float|int $hours, $bookingStart = null): void
    {
        // Nothing to do — canBookHours() always queries live data
    }

    /**
     * No-op: cancellations are reflected automatically in bookedHoursForMonth().
     * Kept for API compatibility with BookingController.
     */
    public function refundCredits(float|int $hours, $bookingStart = null): void
    {
        // Nothing to do — cancelled bookings are excluded by status filter
    }

    /**
     * Remaining hours for the current month, computed live.
     */
    public function getRemainingCreditsAttribute(): int
    {
        if ($this->monthly_credit_limit <= 0) {
            return 999999;
        }

        $now = Carbon::now();
        $used = $this->bookedHoursForMonth($now->year, $now->month);

        return max(0, $this->monthly_credit_limit - $used);
    }

    /**
     * Sum confirmed/active booking hours for a given year+month.
     * This is the single source of truth for credit consumption.
     */
    public function bookedHoursForMonth(int $year, int $month): float
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $bookings = $this->bookings()
            ->whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '>=', $start)
            ->where('start_time', '<=', $end)
            ->get(['start_time', 'end_time']);

        $totalMinutes = 0;
        foreach ($bookings as $booking) {
            $s = Carbon::parse($booking->start_time);
            $e = Carbon::parse($booking->end_time);
            $diff = $s->diffInMinutes($e, false); // false = signed, catches bad data
            if ($diff > 0) {
                $totalMinutes += $diff;
            }
        }

        return ceil($totalMinutes / 60.0);
    }
}
