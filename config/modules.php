<?php

/**
 * Module Configuration
 *
 * Toggle individual modules on/off via environment variables.
 * When a module is disabled its routes are not registered (404),
 * and the GET /api/v1/modules endpoint reflects the current state.
 *
 * All modules default to enabled except where noted.
 */

return [
    'bookings' => env('MODULE_BOOKINGS', true),
    'vehicles' => env('MODULE_VEHICLES', true),
    'absences' => env('MODULE_ABSENCES', true),
    'payments' => env('MODULE_PAYMENTS', true),
    'webhooks' => env('MODULE_WEBHOOKS', true),
    'notifications' => env('MODULE_NOTIFICATIONS', true),
    'branding' => env('MODULE_BRANDING', true),
    'import' => env('MODULE_IMPORT', true),
    'qr_codes' => env('MODULE_QR_CODES', true),
    'favorites' => env('MODULE_FAVORITES', true),
    'swap_requests' => env('MODULE_SWAP_REQUESTS', true),
    'recurring_bookings' => env('MODULE_RECURRING_BOOKINGS', true),
    'zones' => env('MODULE_ZONES', true),
    'credits' => env('MODULE_CREDITS', true),
    'metrics' => env('MODULE_METRICS', true),
    'broadcasting' => env('MODULE_BROADCASTING', true),
    'admin_reports' => env('MODULE_ADMIN_REPORTS', true),
    'data_export' => env('MODULE_DATA_EXPORT', true),
    'setup_wizard' => env('MODULE_SETUP_WIZARD', true),
    'gdpr' => env('MODULE_GDPR', true),
    'push_notifications' => env('MODULE_PUSH_NOTIFICATIONS', true),
    'stripe' => env('MODULE_STRIPE', false), // disabled by default — requires Stripe keys
    'themes' => env('MODULE_THEMES', true),
    'oauth' => env('MODULE_OAUTH', false), // disabled by default — requires OAuth credentials
    'invoices' => env('MODULE_INVOICES', true),
    'dynamic_pricing' => env('MODULE_DYNAMIC_PRICING', true),
    'operating_hours' => env('MODULE_OPERATING_HOURS', true),
    'realtime' => env('MODULE_REALTIME', true),
    'lobby_display' => env('MODULE_LOBBY_DISPLAY', true),
    'analytics' => env('MODULE_ANALYTICS', true),
];
