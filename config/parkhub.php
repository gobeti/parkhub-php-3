<?php

return [
    'demo_mode' => env('DEMO_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    */

    'password_min_length' => (int) env('PASSWORD_MIN_LENGTH', 8),
    'password_require_uppercase' => (bool) env('PASSWORD_REQUIRE_UPPERCASE', true),
    'password_require_number' => (bool) env('PASSWORD_REQUIRE_NUMBER', true),
    'password_require_special' => (bool) env('PASSWORD_REQUIRE_SPECIAL', false),

    /*
    |--------------------------------------------------------------------------
    | Booking Policies
    |--------------------------------------------------------------------------
    */

    'max_advance_days' => (int) env('BOOKING_MAX_ADVANCE_DAYS', 90),
    'min_duration_hours' => (float) env('BOOKING_MIN_DURATION_HOURS', 0.5),
    'max_duration_hours' => (float) env('BOOKING_MAX_DURATION_HOURS', 720),
    'max_active_bookings' => (int) env('BOOKING_MAX_ACTIVE', 10),
];
