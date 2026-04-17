<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Controllers\Api\SystemController;
use App\Providers\ModuleServiceProvider;

/**
 * Declarative metadata registry for every module exposed by the PHP
 * backend. Mirrors `parkhub-server/src/api/modules_meta.rs` in the Rust
 * edition: both backends ship a `{modules, module_info}` envelope on
 * `/api/v1/modules` so the shared frontend (parkhub-web) can render a
 * category-grouped, deep-linkable Modules Dashboard regardless of which
 * backend is answering.
 *
 * Design principle: a single PHP const array is the source of truth.
 * Adding a module means editing ONE table; no scattered registration
 * macros, no cross-file drift. Everything a user or admin sees about
 * modules ships in the binary — nothing calls out to the network.
 *
 * Module slugs follow the PHP config/modules.php convention
 * (snake_case). This keeps `enabled` resolution trivial:
 *   config('modules.'.$name) → bool
 * with a screaming-snake env fallback for slugs that haven't yet been
 * promoted into config/modules.php.
 *
 * v1 of this registry is metadata-only. `runtime_toggleable` is `false`
 * everywhere; v2 will flip the bit for modules that support admin-driven
 * hot toggling. `config_keys` stays empty until v3 wires the per-module
 * admin-settings editor.
 */
final class ModuleRegistry
{
    /**
     * Valid ModuleCategory identifiers — kept as a const array so
     * phpstan + tests can assert category coverage cheaply.
     */
    public const CATEGORIES = [
        'Core',
        'Booking',
        'Vehicle',
        'Admin',
        'Payment',
        'Integration',
        'Analytics',
        'Compliance',
        'Notification',
        'Enterprise',
        'Experimental',
    ];

    /**
     * Declarative module table. Each entry omits `enabled`,
     * `runtime_enabled`, and `version` — those are resolved at
     * materialization time from config / VERSION, so the const stays
     * pure metadata and can be reasoned about statically.
     *
     * @var list<array{
     *     name: string,
     *     category: string,
     *     description: string,
     *     config_keys: list<string>,
     *     ui_route: ?string,
     *     depends_on: list<string>,
     * }>
     */
    private const MODULES = [
        // ── Core ────────────────────────────────────────────────────
        [
            'name' => 'bookings',
            'category' => 'Core',
            'description' => 'Create, edit, cancel, and query reservations.',
            'config_keys' => [],
            'ui_route' => '/bookings',
            'depends_on' => [],
        ],
        [
            'name' => 'vehicles',
            'category' => 'Core',
            'description' => 'User-owned vehicle records with plates and default selection.',
            'config_keys' => [],
            'ui_route' => '/vehicles',
            'depends_on' => [],
        ],
        [
            'name' => 'zones',
            'category' => 'Core',
            'description' => 'Organizational groupings of slots (Level A/B, EV row, visitor row).',
            'config_keys' => [],
            'ui_route' => '/admin/zones',
            'depends_on' => [],
        ],

        // ── Booking-side ───────────────────────────────────────────
        [
            'name' => 'absences',
            'category' => 'Booking',
            'description' => 'Home-office, vacation, sick, training — drives auto-release of booked slots.',
            'config_keys' => [],
            'ui_route' => '/absences',
            'depends_on' => [],
        ],
        [
            'name' => 'absence_approval',
            'category' => 'Booking',
            'description' => 'Manager approval workflow for absence requests.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['absences'],
        ],
        [
            'name' => 'recurring_bookings',
            'category' => 'Booking',
            'description' => 'Weekly / monthly recurring bookings with rule engine.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['bookings'],
        ],
        [
            'name' => 'swap_requests',
            'category' => 'Booking',
            'description' => 'Peer-to-peer booking swaps between users.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['bookings'],
        ],
        [
            'name' => 'waitlist',
            'category' => 'Booking',
            'description' => 'Waitlist notify-on-availability.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'waitlist_ext',
            'category' => 'Booking',
            'description' => 'Advanced waitlist — priority, expiry, multi-slot.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['waitlist'],
        ],
        [
            'name' => 'sharing',
            'category' => 'Booking',
            'description' => 'Shareable booking links with QR + guest registration.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['bookings'],
        ],
        [
            'name' => 'calendar_drag',
            'category' => 'Booking',
            'description' => 'Drag-and-drop reschedule in the calendar view.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'favorites',
            'category' => 'Booking',
            'description' => 'Pin a lot/slot as a favorite for one-tap booking.',
            'config_keys' => [],
            'ui_route' => '/favorites',
            'depends_on' => [],
        ],

        // ── Vehicle / Fleet ────────────────────────────────────────
        [
            'name' => 'fleet',
            'category' => 'Vehicle',
            'description' => 'Fleet admin — shared pool vehicles, utilization reports.',
            'config_keys' => [],
            'ui_route' => '/admin/fleet',
            'depends_on' => [],
        ],
        [
            'name' => 'qr_codes',
            'category' => 'Vehicle',
            'description' => 'QR codes for booking confirmations + slot check-in.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'parking_pass',
            'category' => 'Vehicle',
            'description' => 'Printable / digital parking pass with QR barcode.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],

        // ── Payment ───────────────────────────────────────────────
        [
            'name' => 'payments',
            'category' => 'Payment',
            'description' => 'Generic payment provider abstraction.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'stripe',
            'category' => 'Payment',
            'description' => 'Stripe checkout + webhook integration.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['payments'],
        ],
        [
            'name' => 'credits',
            'category' => 'Payment',
            'description' => 'Virtual credits balance — per-user monthly quota.',
            'config_keys' => [],
            'ui_route' => '/credits',
            'depends_on' => [],
        ],
        [
            'name' => 'invoices',
            'category' => 'Payment',
            'description' => 'Per-booking PDF invoice generation.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'dynamic_pricing',
            'category' => 'Payment',
            'description' => 'Time-of-day and occupancy-based price curves.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],

        // ── Admin ─────────────────────────────────────────────────
        [
            'name' => 'rbac',
            'category' => 'Admin',
            'description' => 'Role-based access control — admin, manager, user, guest.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'sso',
            'category' => 'Admin',
            'description' => 'SAML / OIDC single sign-on for external IdPs.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'audit_log',
            'category' => 'Admin',
            'description' => 'Persistent audit trail of admin-relevant actions.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'audit_export',
            'category' => 'Admin',
            'description' => 'Download the audit log as CSV/JSON.',
            'config_keys' => [],
            'ui_route' => '/admin/audit',
            'depends_on' => ['audit_log'],
        ],
        [
            'name' => 'data_import',
            'category' => 'Admin',
            'description' => 'Bulk import users/vehicles/lots from CSV.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'import',
            'category' => 'Admin',
            'description' => 'Legacy import wrapper retained for API compatibility.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'data_export',
            'category' => 'Admin',
            'description' => 'GDPR + user-initiated data export.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'setup_wizard',
            'category' => 'Admin',
            'description' => 'First-run setup wizard for admins.',
            'config_keys' => [],
            'ui_route' => '/setup',
            'depends_on' => [],
        ],
        [
            'name' => 'rate_dashboard',
            'category' => 'Admin',
            'description' => 'Rate-limit + throttle observability dashboard.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],

        // ── Analytics ────────────────────────────────────────────
        [
            'name' => 'analytics',
            'category' => 'Analytics',
            'description' => 'User-facing analytics — parking history trends.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'admin_analytics',
            'category' => 'Analytics',
            'description' => 'Admin dashboards — bookings, occupancy, revenue.',
            'config_keys' => [],
            'ui_route' => '/admin/analytics',
            'depends_on' => [],
        ],
        [
            'name' => 'admin_reports',
            'category' => 'Analytics',
            'description' => 'Canned admin reports — monthly usage, top users.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'scheduled_reports',
            'category' => 'Analytics',
            'description' => 'Email cron for recurring admin reports.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['admin_reports'],
        ],
        [
            'name' => 'metrics',
            'category' => 'Analytics',
            'description' => 'Prometheus metrics exporter for scrape-based monitoring.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],

        // ── Integration ─────────────────────────────────────────
        [
            'name' => 'webhooks',
            'category' => 'Integration',
            'description' => 'Outbound webhooks (v1 — fire and forget).',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'webhooks_v2',
            'category' => 'Integration',
            'description' => 'Outbound webhooks with retry + signed payloads.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'graphql',
            'category' => 'Integration',
            'description' => 'GraphQL read/write API alongside REST.',
            'config_keys' => [],
            'ui_route' => '/admin/graphql',
            'depends_on' => [],
        ],
        [
            'name' => 'api_docs',
            'category' => 'Integration',
            'description' => 'Swagger UI + OpenAPI 3.1 spec export.',
            'config_keys' => [],
            'ui_route' => '/api/docs',
            'depends_on' => [],
        ],
        [
            'name' => 'api_versioning',
            'category' => 'Integration',
            'description' => 'v1/v2 API surface with deprecation headers.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'oauth',
            'category' => 'Integration',
            'description' => 'OAuth2 callback handling (Google, GitHub, Microsoft).',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'ical',
            'category' => 'Integration',
            'description' => 'Read-only iCal feed of bookings for calendar clients.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'broadcasting',
            'category' => 'Integration',
            'description' => 'WebSocket broadcast for real-time occupancy + booking events.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'realtime',
            'category' => 'Integration',
            'description' => 'Server-Sent Events + presence channel for collaborative UI.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'widgets',
            'category' => 'Integration',
            'description' => 'Embeddable widgets for external dashboards.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],

        // ── Notification ────────────────────────────────────────
        [
            'name' => 'notifications',
            'category' => 'Notification',
            'description' => 'In-app notification bell with per-event preferences.',
            'config_keys' => [],
            'ui_route' => '/notifications',
            'depends_on' => [],
        ],
        [
            'name' => 'notification_center',
            'category' => 'Notification',
            'description' => 'Grouped notification feed with mark-read.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['notifications'],
        ],
        [
            'name' => 'push_notifications',
            'category' => 'Notification',
            'description' => 'Web Push subscription + VAPID delivery.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'web_push',
            'category' => 'Notification',
            'description' => 'Lower-level Web Push plumbing shared by notification channels.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],

        // ── Compliance ──────────────────────────────────────────
        [
            'name' => 'compliance',
            'category' => 'Compliance',
            'description' => 'Compliance tooling — export, evidence, attestations.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'accessible',
            'category' => 'Compliance',
            'description' => 'Accessible-slots policy + user accessibility needs.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'gdpr',
            'category' => 'Compliance',
            'description' => 'GDPR consent ledger, data-subject requests, right-to-be-forgotten.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],

        // ── Enterprise ──────────────────────────────────────────
        [
            'name' => 'multi_tenant',
            'category' => 'Enterprise',
            'description' => 'Multi-tenant isolation — per-tenant users, lots, branding.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'cost_center',
            'category' => 'Enterprise',
            'description' => 'Per-cost-center reporting + allocation.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'themes',
            'category' => 'Enterprise',
            'description' => 'Per-tenant theme / branding customization.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'plugins',
            'category' => 'Enterprise',
            'description' => 'Runtime plugin loader for customer-side extensions.',
            'config_keys' => [],
            'ui_route' => '/admin/plugins',
            'depends_on' => [],
        ],
        [
            'name' => 'branding',
            'category' => 'Enterprise',
            'description' => 'App name + logo customization.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'parking_zones',
            'category' => 'Enterprise',
            'description' => 'Advanced zone management + rules.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => ['zones'],
        ],

        // ── Experimental / Hardware ─────────────────────────────
        [
            'name' => 'map',
            'category' => 'Experimental',
            'description' => 'Map view of lots with live occupancy overlay.',
            'config_keys' => [],
            'ui_route' => '/map',
            'depends_on' => [],
        ],
        [
            'name' => 'geofence',
            'category' => 'Experimental',
            'description' => 'GPS-geofenced auto check-in.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'visitors',
            'category' => 'Experimental',
            'description' => 'Visitor badge + pre-registration.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'maintenance',
            'category' => 'Experimental',
            'description' => 'Slot maintenance windows + technician scheduling.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'history',
            'category' => 'Experimental',
            'description' => 'Extended parking history view for users.',
            'config_keys' => [],
            'ui_route' => '/history',
            'depends_on' => [],
        ],
        [
            'name' => 'ev_charging',
            'category' => 'Experimental',
            'description' => 'EV-charging slots + charging-session metadata.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'recommendations',
            'category' => 'Experimental',
            'description' => 'Slot recommendations based on user history.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'operating_hours',
            'category' => 'Experimental',
            'description' => 'Lot operating-hours enforcement.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'lobby_display',
            'category' => 'Experimental',
            'description' => 'Kiosk / lobby-display mode for public screens.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'enhanced_pwa',
            'category' => 'Experimental',
            'description' => 'Enhanced PWA with booking prefetch and offline data.',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
        [
            'name' => 'mobile',
            'category' => 'Experimental',
            'description' => 'Mobile-specific surfaces (install prompt, haptics).',
            'config_keys' => [],
            'ui_route' => null,
            'depends_on' => [],
        ],
    ];

    /**
     * Return every module as a materialized ModuleInfo shape. The order
     * matches the const declaration above so the admin dashboard stays
     * stable across deploys.
     *
     * @return list<array{
     *     name: string,
     *     category: string,
     *     description: string,
     *     enabled: bool,
     *     runtime_toggleable: bool,
     *     runtime_enabled: bool,
     *     config_keys: list<string>,
     *     ui_route: ?string,
     *     depends_on: list<string>,
     *     version: string,
     * }>
     */
    public static function all(): array
    {
        $version = SystemController::appVersion();

        return array_map(
            static fn (array $meta): array => self::materialize($meta, $version),
            self::MODULES,
        );
    }

    /**
     * Fetch a single module by slug. Returns null for unknown modules,
     * matching the 404 semantics the controller needs.
     *
     * @return array{
     *     name: string,
     *     category: string,
     *     description: string,
     *     enabled: bool,
     *     runtime_toggleable: bool,
     *     runtime_enabled: bool,
     *     config_keys: list<string>,
     *     ui_route: ?string,
     *     depends_on: list<string>,
     *     version: string,
     * }|null
     */
    public static function get(string $name): ?array
    {
        foreach (self::MODULES as $meta) {
            if ($meta['name'] === $name) {
                return self::materialize($meta, SystemController::appVersion());
            }
        }

        return null;
    }

    /**
     * Project the `{module => bool}` map expected by the existing
     * `/api/v1/modules` consumers. Combines the registry with any
     * extra slugs declared only in config/modules.php so no module
     * disappears from the backwards-compat envelope.
     *
     * @return array<string, bool>
     */
    public static function enabledMap(): array
    {
        $map = [];

        foreach (self::MODULES as $meta) {
            $map[$meta['name']] = self::resolveEnabled($meta['name']);
        }

        foreach (ModuleServiceProvider::all() as $name => $enabled) {
            if (! array_key_exists($name, $map)) {
                $map[$name] = (bool) $enabled;
            }
        }

        return $map;
    }

    /**
     * @param  array{
     *     name: string,
     *     category: string,
     *     description: string,
     *     config_keys: list<string>,
     *     ui_route: ?string,
     *     depends_on: list<string>,
     * }  $meta
     * @return array{
     *     name: string,
     *     category: string,
     *     description: string,
     *     enabled: bool,
     *     runtime_toggleable: bool,
     *     runtime_enabled: bool,
     *     config_keys: list<string>,
     *     ui_route: ?string,
     *     depends_on: list<string>,
     *     version: string,
     * }
     */
    private static function materialize(array $meta, string $version): array
    {
        $enabled = self::resolveEnabled($meta['name']);

        return [
            'name' => $meta['name'],
            'category' => $meta['category'],
            'description' => $meta['description'],
            'enabled' => $enabled,
            // v1: no runtime override layer — kept as false everywhere.
            // v2 will flip this per-module once admin-settings bridge lands.
            'runtime_toggleable' => false,
            'runtime_enabled' => $enabled,
            'config_keys' => $meta['config_keys'],
            'ui_route' => $meta['ui_route'],
            'depends_on' => $meta['depends_on'],
            'version' => $version,
        ];
    }

    /**
     * Resolve enabled state from the canonical config source.
     *
     * `config('modules.'.$name)` is what the rest of the runtime checks
     * (route guards, middleware, feature flags). Sticking to that single
     * source of truth keeps the registry honest when config is cached
     * and sidesteps Larastan's `env()`-outside-of-config rule.
     *
     * Defaults to true so newly registered slugs that haven't yet been
     * promoted into config/modules.php appear enabled, matching how
     * compile-time features default on in the Rust edition.
     */
    private static function resolveEnabled(string $name): bool
    {
        /** @var bool|int|string|null $configured */
        $configured = config('modules.'.$name);
        if ($configured === null) {
            return true;
        }

        return (bool) $configured;
    }
}
