<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Webhook;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    private function requireAdmin($request): void
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            abort(403, 'Admin access required');
        }
    }

    public function getSettings(Request $request)
    {
        $this->requireAdmin($request);

        $settings = Setting::all()->pluck('value', 'key')->toArray();
        $defaults = [
            'company_name' => 'ParkHub',
            'use_case' => 'corporate',
            'self_registration' => 'true',
            'license_plate_mode' => 'optional',
            'display_name_format' => 'first_name',
            'max_bookings_per_day' => '3',
            'allow_guest_bookings' => 'false',
            'auto_release_minutes' => '30',
            'require_vehicle' => 'false',
            'waitlist_enabled' => 'true',
            'min_booking_duration_hours' => '0',
            'max_booking_duration_hours' => '0',
            'credits_enabled' => 'false',
            'credits_per_booking' => '1',
            'primary_color' => '#d97706',
            'secondary_color' => '#475569',
        ];

        return response()->json(array_merge($defaults, $settings));
    }

    public function updateSettings(Request $request)
    {
        $this->requireAdmin($request);

        // Allowlist of keys that can be set via this endpoint.
        // Prevents injection of arbitrary/internal settings keys.
        $allowed = [
            'company_name', 'use_case', 'self_registration', 'license_plate_mode',
            'display_name_format', 'max_bookings_per_day', 'allow_guest_bookings',
            'auto_release_minutes', 'require_vehicle', 'waitlist_enabled',
            'min_booking_duration_hours', 'max_booking_duration_hours',
            'credits_enabled', 'credits_per_booking',
            'primary_color', 'secondary_color',
        ];

        // Normalize string booleans before validation so Laravel's boolean rule accepts them
        $booleanKeys = ['self_registration', 'allow_guest_bookings', 'require_vehicle', 'waitlist_enabled', 'credits_enabled'];
        foreach ($booleanKeys as $bk) {
            if ($request->has($bk)) {
                $val = $request->input($bk);
                if ($val === 'true' || $val === '1') {
                    $request->merge([$bk => true]);
                } elseif ($val === 'false' || $val === '0') {
                    $request->merge([$bk => false]);
                }
            }
        }

        $request->validate([
            'company_name' => 'sometimes|string|max:255',
            'use_case' => 'sometimes|in:corporate,university,residential,other',
            'self_registration' => 'sometimes|boolean',
            'license_plate_mode' => 'sometimes|in:required,optional,disabled,visible,hidden',
            'display_name_format' => 'sometimes|in:first_name,full_name,username',
            'max_bookings_per_day' => 'sometimes|integer|min:0|max:50',
            'allow_guest_bookings' => 'sometimes|boolean',
            'auto_release_minutes' => 'sometimes|integer|min:0|max:480',
            'require_vehicle' => 'sometimes|boolean',
            'waitlist_enabled' => 'sometimes|boolean',
            'min_booking_duration_hours' => 'sometimes|numeric|min:0|max:24',
            'max_booking_duration_hours' => 'sometimes|numeric|min:0|max:72',
            'credits_enabled' => 'sometimes|boolean',
            'credits_per_booking' => 'sometimes|integer|min:1|max:100',
            'primary_color' => 'sometimes|string|regex:/^#[0-9a-fA-F]{6}$/',
            'secondary_color' => 'sometimes|string|regex:/^#[0-9a-fA-F]{6}$/',
        ]);

        foreach ($request->only($allowed) as $key => $value) {
            // Normalize booleans to 'true'/'false' strings for consistent Setting::get() checks
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            Setting::set($key, is_array($value) ? json_encode($value) : (string) $value);
        }

        return response()->json(['message' => 'Settings updated']);
    }

    public function getBranding(Request $request)
    {
        $this->requireAdmin($request);

        return response()->json([
            'company_name' => Setting::get('company_name', 'ParkHub'),
            'primary_color' => Setting::get('brand_primary_color', '#3b82f6'),
            'logo_url' => Setting::get('logo_url', null),
            'use_case' => Setting::get('use_case', 'corporate'),
        ]);
    }

    public function updateBranding(Request $request)
    {
        $this->requireAdmin($request);
        foreach (['company_name', 'primary_color', 'logo_url', 'use_case'] as $key) {
            if ($request->has($key)) {
                Setting::set('brand_'.$key, $request->input($key));
            }
        }
        if ($request->has('company_name')) {
            Setting::set('company_name', $request->input('company_name'));
        }
        if ($request->has('use_case')) {
            Setting::set('use_case', $request->input('use_case'));
        }

        return response()->json(['message' => 'Branding updated']);
    }

    public function uploadBrandingLogo(Request $request)
    {
        $this->requireAdmin($request);
        $request->validate(['logo' => 'required|image|max:2048']);
        $path = $request->file('logo')->store('branding', 'public');
        Setting::set('logo_url', '/storage/'.$path);

        return response()->json(['logo_url' => '/storage/'.$path]);
    }

    public function serveBrandingLogo(Request $request)
    {
        $logoUrl = Setting::get('logo_url', null);
        if (! $logoUrl) {
            // Return default SVG icon
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="#3b82f6"><circle cx="32" cy="32" r="30"/><text x="32" y="42" text-anchor="middle" font-size="32" fill="white" font-family="Arial" font-weight="bold">P</text></svg>';

            return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400']);
        }
        if (str_starts_with($logoUrl, '/storage/')) {
            $filePath = storage_path('app/public'.substr($logoUrl, 8));
            if (file_exists($filePath)) {
                return response()->file($filePath, ['Cache-Control' => 'public, max-age=86400']);
            }
        }

        return redirect($logoUrl);
    }

    public function getPrivacy(Request $request)
    {
        $this->requireAdmin($request);

        return response()->json([
            'policy_text' => Setting::get('privacy_policy', ''),
            'data_retention_days' => (int) Setting::get('data_retention_days', 365),
            'gdpr_enabled' => Setting::get('gdpr_enabled', 'true') === 'true',
        ]);
    }

    public function updatePrivacy(Request $request)
    {
        $this->requireAdmin($request);
        foreach (['policy_text', 'data_retention_days', 'gdpr_enabled'] as $key) {
            if ($request->has($key)) {
                Setting::set('privacy_'.str_replace('_text', '_policy', $key), (string) $request->input($key));
            }
        }
        if ($request->has('policy_text')) {
            Setting::set('privacy_policy', $request->input('policy_text'));
        }
        if ($request->has('data_retention_days')) {
            Setting::set('data_retention_days', $request->input('data_retention_days'));
        }
        if ($request->has('gdpr_enabled')) {
            Setting::set('gdpr_enabled', $request->boolean('gdpr_enabled') ? 'true' : 'false');
        }

        return response()->json(['message' => 'Privacy settings updated']);
    }

    public function getAutoReleaseSettings(Request $request)
    {
        $this->requireAdmin($request);

        return response()->json([
            'enabled' => Setting::get('auto_release_enabled', 'false') === 'true',
            'timeout_minutes' => (int) Setting::get('auto_release_timeout', 30),
        ]);
    }

    public function updateAutoReleaseSettings(Request $request)
    {
        $this->requireAdmin($request);
        if ($request->has('enabled')) {
            Setting::set('auto_release_enabled', $request->boolean('enabled') ? 'true' : 'false');
        }
        if ($request->has('timeout_minutes')) {
            Setting::set('auto_release_timeout', $request->input('timeout_minutes'));
        }

        return response()->json(['message' => 'Auto-release settings updated']);
    }

    public function getEmailSettings(Request $request)
    {
        $this->requireAdmin($request);

        return response()->json([
            'smtp_host' => Setting::get('smtp_host', ''),
            'smtp_port' => (int) Setting::get('smtp_port', 587),
            'smtp_user' => Setting::get('smtp_user', ''),
            'from_email' => Setting::get('from_email', ''),
            'from_name' => Setting::get('from_name', 'ParkHub'),
            'enabled' => Setting::get('email_enabled', 'false') === 'true',
        ]);
    }

    public function updateEmailSettings(Request $request)
    {
        $this->requireAdmin($request);
        foreach (['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'from_email', 'from_name'] as $key) {
            if ($request->has($key)) {
                Setting::set($key, $request->input($key));
            }
        }
        if ($request->has('enabled')) {
            Setting::set('email_enabled', $request->boolean('enabled') ? 'true' : 'false');
        }

        return response()->json(['message' => 'Email settings updated']);
    }

    public function getWebhookSettings(Request $request)
    {
        $this->requireAdmin($request);
        $hooks = Webhook::all();

        return response()->json($hooks);
    }

    public function updateWebhookSettings(Request $request)
    {
        $this->requireAdmin($request);
        if ($request->has('webhooks')) {
            Webhook::query()->delete();
            foreach ($request->input('webhooks') as $hook) {
                $url = $hook['url'] ?? '';
                // SSRF protection: reject private/internal IP ranges
                if (! $this->isExternalUrl($url)) {
                    return response()->json(['error' => 'SSRF_BLOCKED', 'message' => 'Webhook URL must not target internal/private networks'], 422);
                }
                Webhook::create([
                    'url' => $url,
                    'events' => $hook['events'] ?? [],
                    'secret' => $hook['secret'] ?? null,
                    'active' => $hook['active'] ?? true,
                ]);
            }
        }

        return response()->json(['message' => 'Webhook settings updated']);
    }

    // ── Impressum (DDG § 5 — legally required for German operators) ──────────

    public function getImpress(Request $request)
    {
        $this->requireAdmin($request);

        return response()->json([
            'provider_name' => Setting::get('impressum_provider_name', ''),
            'provider_legal_form' => Setting::get('impressum_legal_form', ''),
            'street' => Setting::get('impressum_street', ''),
            'zip_city' => Setting::get('impressum_zip_city', ''),
            'country' => Setting::get('impressum_country', 'Deutschland'),
            'email' => Setting::get('impressum_email', ''),
            'phone' => Setting::get('impressum_phone', ''),
            'register_court' => Setting::get('impressum_register_court', ''),
            'register_number' => Setting::get('impressum_register_number', ''),
            'vat_id' => Setting::get('impressum_vat_id', ''),
            'responsible_person' => Setting::get('impressum_responsible', ''),
            'custom_text' => Setting::get('impressum_custom_text', ''),
        ]);
    }

    public function updateImpress(Request $request)
    {
        $this->requireAdmin($request);
        $fields = [
            'provider_name', 'provider_legal_form', 'street', 'zip_city', 'country',
            'email', 'phone', 'register_court', 'register_number', 'vat_id',
            'responsible_person', 'custom_text',
        ];
        $keyMap = [
            'provider_name' => 'impressum_provider_name',
            'provider_legal_form' => 'impressum_legal_form',
            'street' => 'impressum_street',
            'zip_city' => 'impressum_zip_city',
            'country' => 'impressum_country',
            'email' => 'impressum_email',
            'phone' => 'impressum_phone',
            'register_court' => 'impressum_register_court',
            'register_number' => 'impressum_register_number',
            'vat_id' => 'impressum_vat_id',
            'responsible_person' => 'impressum_responsible',
            'custom_text' => 'impressum_custom_text',
        ];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                Setting::set($keyMap[$field], (string) $request->input($field));
            }
        }
        AuditLog::log([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'impressum_updated',
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Impressum updated']);
    }

    // Route-named aliases expected by api.php
    public function getImpressum(Request $request)
    {
        return $this->getImpress($request);
    }

    public function updateImpressum(Request $request)
    {
        return $this->updateImpress($request);
    }

    // Public Impressum endpoint (no auth — must be accessible to all visitors)
    public function publicImpress()
    {
        return response()->json([
            'provider_name' => Setting::get('impressum_provider_name', ''),
            'provider_legal_form' => Setting::get('impressum_legal_form', ''),
            'street' => Setting::get('impressum_street', ''),
            'zip_city' => Setting::get('impressum_zip_city', ''),
            'country' => Setting::get('impressum_country', 'Deutschland'),
            'email' => Setting::get('impressum_email', ''),
            'phone' => Setting::get('impressum_phone', ''),
            'register_court' => Setting::get('impressum_register_court', ''),
            'register_number' => Setting::get('impressum_register_number', ''),
            'vat_id' => Setting::get('impressum_vat_id', ''),
            'responsible_person' => Setting::get('impressum_responsible', ''),
            'custom_text' => Setting::get('impressum_custom_text', ''),
        ]);
    }

    /**
     * Validate that a URL does not target internal/private networks (SSRF protection).
     */
    private function isExternalUrl(string $url): bool
    {
        // Must be http or https
        if (! preg_match('#^https?://#i', $url)) {
            return false;
        }

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        if (empty($host)) {
            return false;
        }

        // Resolve hostname to IP(s)
        $ips = gethostbynamel($host);
        if ($ips === false) {
            // Unresolvable hostname — block to be safe
            return false;
        }

        foreach ($ips as $ip) {
            if ($this->isPrivateIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private function isPrivateIp(string $ip): bool
    {
        // Loopback: 127.0.0.0/8
        // RFC1918: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
        // Link-local: 169.254.0.0/16
        // IPv6 mapped: ::1, ::ffff:127.0.0.1, etc.
        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    public function resetDatabase(Request $request)
    {
        $this->requireAdmin($request);
        $request->validate(['confirm' => 'required|in:RESET']);
        // Delete all user data but keep admin account
        $admin = $request->user();
        Booking::query()->delete();
        Absence::query()->delete();
        Vehicle::query()->delete();
        ParkingSlot::query()->update(['status' => 'available']);
        User::where('id', '!=', $admin->id)->delete();
        AuditLog::log([
            'user_id' => $admin->id,
            'username' => $admin->username,
            'action' => 'database_reset',
        ]);

        return response()->json(['message' => 'Database reset. All user data deleted.']);
    }
}
