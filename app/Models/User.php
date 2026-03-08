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
    //   Monthly Credit System
    // ────────────────────────────────────────────────

    /**
     * Check if the user can book the requested hours for the given month.
     * - Current month: uses monthly_credits_used (fast, cached on user row)
     * - Future month: queries bookings table for that month (always fresh)
     */
    public function canBookHours(float|int $hours, $bookingStart = null): bool
    {
        if ($this->monthly_credit_limit <= 0) {
            return true; // no limit configured
        }

        $bookingStart = $bookingStart ?? Carbon::now();
        $now = Carbon::now();

        $isCurrentMonth = $bookingStart->month === $now->month
                       && $bookingStart->year  === $now->year;

        if ($isCurrentMonth) {
            $this->refreshCreditsIfNeeded();
            $used = $this->monthly_credits_used;
        } else {
            // For future months, count hours already booked via the bookings table
            $used = $this->bookedHoursForMonth($bookingStart->year, $bookingStart->month);
        }

        return ($used + $hours) <= $this->monthly_credit_limit;
    }

    /**
     * Consume credits after a successful booking.
     * Only updates monthly_credits_used for current-month bookings.
     * Future-month bookings are tracked implicitly via the bookings table.
     */
    public function useCredits(float|int $hours, $bookingStart = null): void
    {
        if ($this->monthly_credit_limit <= 0) {
            return;
        }

        $bookingStart = $bookingStart ?? Carbon::now();
        $now = Carbon::now();

        $isCurrentMonth = $bookingStart->month === $now->month
                       && $bookingStart->year  === $now->year;

        if ($isCurrentMonth) {
            $this->refreshCreditsIfNeeded();
            $this->increment('monthly_credits_used', (int) ceil($hours));
        }
        // Future months: no increment needed — canBookHours() queries the DB directly
    }

    /**
     * Refund credits when a booking is cancelled.
     * Only affects monthly_credits_used for current-month bookings.
     */
    public function refundCredits(float|int $hours, $bookingStart = null): void
    {
        if ($this->monthly_credit_limit <= 0) {
            return;
        }

        $bookingStart = $bookingStart ?? Carbon::now();
        $now = Carbon::now();

        $isCurrentMonth = $bookingStart->month === $now->month
                       && $bookingStart->year  === $now->year;

        if ($isCurrentMonth) {
            $refund = min((int) ceil($hours), $this->monthly_credits_used);
            if ($refund > 0) {
                $this->decrement('monthly_credits_used', $refund);
            }
        }
        // Future months: no change needed — canBookHours() will re-query the DB
    }

    /**
     * Get remaining hours the user can still book this month.
     */
    public function getRemainingCreditsAttribute(): int
    {
        if ($this->monthly_credit_limit <= 0) {
            return 999999;
        }

        $this->refreshCreditsIfNeeded();

        return max(0, $this->monthly_credit_limit - $this->monthly_credits_used);
    }

    /**
     * Sum of confirmed/active booking hours for a given year+month.
     */
    public function bookedHoursForMonth(int $year, int $month): int
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
            $totalMinutes += $s->diffInMinutes($e);
        }

        return (int) ceil($totalMinutes / 60.0);
    }

    /**
     * Reset used credits if we are in a new calendar month.
     */
    protected function refreshCreditsIfNeeded(): void
    {
        $now = Carbon::now();

        $shouldReset = !$this->credits_reset_at ||
                       $this->credits_reset_at->month !== $now->month ||
                       $this->credits_reset_at->year  !== $now->year;

        if ($shouldReset) {
            $this->updateQuietly([
                'monthly_credits_used' => 0,
                'credits_reset_at'     => $now,
            ]);
        }
    }
}
