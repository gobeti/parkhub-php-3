<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Carbon;

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
        // ── NEW ── monthly credit fields
        'monthly_credit_limit',
        'monthly_credits_used',
        'credits_reset_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'preferences'         => 'array',
            'is_active'           => 'boolean',
            'last_login'          => 'datetime',
            'email_verified_at'   => 'datetime',
            // ── NEW ── cast timestamps properly
            'credits_reset_at'    => 'datetime',
            'monthly_credit_limit' => 'integer',
            'monthly_credits_used' => 'integer',
        ];
    }

    // ────────────────────────────────────────────────
    //   Relationships (existing)
    // ────────────────────────────────────────────────
    public function bookings()           { return $this->hasMany(Booking::class); }
    public function vehicles()           { return $this->hasMany(Vehicle::class); }
    public function absences()           { return $this->hasMany(Absence::class); }
    public function notifications_list() { return $this->hasMany(Notification::class); }
    public function favorites()          { return $this->hasMany(Favorite::class); }
    public function recurringBookings()  { return $this->hasMany(RecurringBooking::class); }

    // ────────────────────────────────────────────────
    //   Role helper (existing)
    // ────────────────────────────────────────────────
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'superadmin']);
    }

    // ────────────────────────────────────────────────
    //   Monthly Credit System
    // ────────────────────────────────────────────────

    /**
     * Check if the user is allowed to book the requested number of hours
     * this month according to their remaining credits.
     */
    public function canBookHours(float|int $hours): bool
    {
        if ($this->monthly_credit_limit <= 0) {
            return true; // no limit configured
        }

        $this->refreshCreditsIfNeeded();

        $requestedTotal = $this->monthly_credits_used + $hours;

        return $requestedTotal <= $this->monthly_credit_limit;
    }

    /**
     * Consume credits after a successful booking is created.
     * We use ceil() so even 1 minute of parking costs a full hour credit.
     */
    public function useCredits(float|int $hours): void
    {
        if ($this->monthly_credit_limit <= 0) {
            return;
        }

        $this->refreshCreditsIfNeeded();

        $this->increment('monthly_credits_used', (int) ceil($hours));
    }

    /**
     * Get remaining hours the user can still book this month.
     * Useful for frontend display.
     */
    public function getRemainingCreditsAttribute(): int
    {
        if ($this->monthly_credit_limit <= 0) {
            return 999999; // effectively unlimited
        }

        $this->refreshCreditsIfNeeded();

        return $this->monthly_credit_limit - $this->monthly_credits_used;
    }

    /**
     * Reset used credits if we are in a new calendar month.
     * Called automatically before any check/use operation.
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
