# Changelog

All notable changes to ParkHub PHP are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [3.0.0] - 2026-03-22

### Added
- **10-Language i18n**: Full internationalization with EN, DE, FR, ES, IT, PT, TR, PL, JA, ZH locale files
  - Language selector dropdown in sidebar Layout component
  - i18n tests validate all 10 locales for missing keys and empty string values
- **Admin Analytics module**: `GET /api/v1/admin/analytics/overview` endpoint
  - Daily bookings (30d), revenue by day, peak hours (24 bins), top lots, user growth (12mo), avg duration
  - DB-agnostic: SQLite, PostgreSQL, MySQL support
  - `MODULE_ANALYTICS=true` toggle (30th module)
  - 9 PHP tests for auth, data structure, accuracy, and module toggle
- **AdminAnalytics frontend view**: Analytics dashboard synced from parkhub-rust with route at `/admin/analytics`
- 488 vitest + 942 PHPUnit = **1430 tests** total

### Changed
- Module count: 30 modules (added `analytics`)
- README badges updated to v3.0.0
- Frontend fully synced with parkhub-rust (all locale files, i18n index, Layout, App.tsx, Admin views)

---

## [2.9.0] - 2026-03-22

### Added
- **Lobby Display / Kiosk Mode**: Public `GET /api/v1/lots/{id}/display` endpoint (no auth) for full-screen parking monitors
  - Real-time occupancy with color status (green/yellow/red), floor breakdown, 10 req/min throttle
  - `MODULE_LOBBY_DISPLAY` toggle, `LobbyDisplayController`, route at `/lobby/:lotId`
  - Frontend `LobbyDisplayPage` synced from Rust edition with full-screen dark UI
  - 8 PHP tests, 6 frontend tests, i18n (en/de)
- **Onboarding Wizard**: 4-step guided setup flow via `SetupWizardController`
  - Step 1: Company info + timezone + logo
  - Step 2: Lot creation with auto-generated floors (zones) and slots
  - Step 3: User invitations via email
  - Step 4: Theme selection from 12 themes, marks wizard complete
  - `GET /api/v1/setup/wizard/status` + `POST /api/v1/setup/wizard`
  - Frontend `SetupWizardPage` synced from Rust with progress bar and validation
  - 9 PHP tests, 6 frontend tests, i18n (en/de)

### Changed
- Module count: 29 modules (added `lobby_display`)
- Rate limiter `lobby-display`: 10 requests/min per IP
- App.tsx: Added `/lobby/:lotId` and `/setup` public routes

---

## [2.8.0] - 2026-03-22

### Added
- **SSE Real-Time module**: Server-Sent Events endpoint (`GET /api/v1/sse`) for streaming booking and occupancy events
- **SSE status endpoint**: `GET /api/v1/sse/status` with module info and pending event count
- **PushSseBookingEvent listener**: Automatic cache-queue push on BookingCreated/BookingCancelled events
- **OccupancyChanged event**: Broadcast event for lot availability changes
- **MODULE_REALTIME**: New module toggle (28 modules total)
- **WebSocket hook sync from Rust**: Occupancy map, token auth, maxReconnectDelay, 5 event types
- **Live indicator**: Green dot + "Live" text on Dashboard when WebSocket connected
- **Bookings real-time toasts**: Toast notifications for booking_created/booking_cancelled events
- **Dynamic pricing API**: Client methods for lot pricing endpoints
- **Operating hours API**: Client methods for lot hours endpoints
- 13 new PHP tests (SseRealtimeTest), 14 frontend WebSocket tests, 2 WS indicator tests

### Changed
- Frontend fully synced with parkhub-rust v28 (9 files)
- Dashboard.tsx: occupancy destructuring, live connection indicator
- Bookings.tsx: WebSocket event handler integration
- client.ts: DynamicPricing + OperatingHours types and methods
- AdminLots.tsx, Book.tsx, Profile.tsx, NotificationPreferences.tsx synced

---

## [2.2.0] - 2026-03-22

### Added
- **Glass morphism UI**: Bento grid dashboard with frosted-glass cards, animated counters, and modern gradients
- **2FA/TOTP authentication**: QR code enrollment, backup codes, per-account enable/disable
- **Security improvements**: Login history, session management, API key management, notification preferences
- **CI badges and GitOps polish**: README overhaul, SECURITY.md, issue/PR templates

### Changed
- Bumped version to 2.2.0
- README badges switched to flat-square style with CI status badge
- Added Security link to README navigation

---

## [2.1.0] - 2026-03-22

### Added
- **22 controller modules**: Full module system documentation with controller mapping
- **Bulk admin operations**: Bulk user actions, advanced reports, booking policies
- **Health monitoring**: Enhanced health checks and system status endpoints

### Changed
- Frontend synced with parkhub-rust v2.2.0 (glass morphism, bento grid)

---

## [2.0.0] - 2026-03-22

### Added
- **Full module system**: 22 controller modules documented and organized
- **Smart slot recommendations**: Heuristic scoring engine — top 5 returned
- **Community translation management**: Proposal submission, up/down voting, admin review
- **Runtime translation overrides**: Approved translations hot-loaded at startup
- **Favorites UI**: Pinned parking slots with live availability
- **Dashboard analytics**: 7-day booking activity bar chart
- **DataTable CSV export**: CSV download with proper escaping
- **Demo reset tracking**: Cache-based status tracking with 9 tests

### Changed
- Major version bump to align with Rust edition versioning

### Tests
- **484 PHP (1094 assertions) + 401 Frontend** = 885 total tests

---

## [1.9.0] - 2026-03-21

### Added
- **Community translation management**: Proposal submission, up/down voting, admin review (approve/reject with comments)
- **Runtime translation overrides**: Approved translations hot-loaded into i18n at app startup
- **Smart slot recommendations**: Heuristic scoring engine (slot frequency, lot frequency, features, proximity, base) — top 5 returned
- **Favorites UI**: Full view for managing pinned parking slots with live availability status
- **Dashboard analytics**: 7-day booking activity bar chart with real booking data
- **DataTable CSV export**: Download any data table as CSV with proper cell escaping
- **A11y audit fixes**: ARIA labels, contrast fixes, confirm dialogs replacing window.confirm
- **Demo reset tracking**: Cache-based `last_reset_at`, `next_scheduled_reset`, `reset_in_progress` with 9 tests
- **i18n**: 10 languages with favorites section (150+ keys per locale)

### Changed
- Removed unused `ParkingSlot` import from RecommendationController
- API response format standardized across translation endpoints
- Version bumped to 1.9.0

### Tests
- **484 PHP (1094 assertions) + 401 Frontend** = 885 total tests

---

## [1.6.0] - 2026-03-20

### Added
- **Typed error handling**: Consistent structured error responses across all endpoints
- **Demo reset with DB wipe**: Full database truncate and re-seed on demo reset
- **Auto-reset scheduler (6h)**: Demo mode auto-resets every 6 hours via Laravel scheduler
- **React 19 useActionState**: Form handling migrated to `useActionState` pattern
- **Tailwind CSS 4 @utility**: Custom utilities via `@utility` directives
- **Admin user search**: Search/filter users by name, email, or role in admin panel
- **Rate-limited demo endpoints**: Demo reset and status endpoints are rate-limited

### Tests
- **965 tests total**: 326 PHP + 213 Vitest + 426 Rust (up from 434 in v1.4.8)

---

## [1.4.8] - 2026-03-19

### Design
- **Full UI overhaul**: Eliminated AI slop patterns across all views
- Welcome, Login, Dashboard, Bookings, Profile, Admin — all redesigned
- System font, tight tracking, 12px cards, 8px buttons, solid backgrounds
- Left-aligned layouts, no floating shapes, no icons-in-circles

### Added
- **434 tests**: 150 PHP (376 assertions) + 137 frontend vitest + 147 Rust
- **Maestro E2E**: 5 browser flows
- **Skeleton loading, micro-interactions, animated stats**
- **i18n**: 50+ keys (EN + DE) including nav.team, nav.calendar, nav.notifications
- **Dynamic version from package.json**

### Fixed
- Setup wizard admin role ($fillable missing 'role')
- DemoController wrong config key (test_mode → demo_mode)
- Docker entrypoint .env override for env vars
- GDPR anonymize audit_log table name
- FeaturesContext crash (missing API method)
- 2 Dependabot vulnerabilities (flatted, league/commonmark)

---

## [1.3.7] - 2026-03-19

### Added
- **Vehicle tests**: 9 feature tests covering CRUD, ownership isolation, validation, auth guard
- **Setup tests**: 7 feature tests covering wizard status, admin detection, init flow, validation
- **Health tests**: 5 feature tests covering liveness, readiness, health check, auth-free access
- **Notification tests**: 6 feature tests covering list, ownership, mark-as-read, ordering
- **Frontend Vitest tests**: 33 tests across 3 files (API client, DemoOverlay, Login)
- **i18n keys**: Added `useCase.*` and `features.*` translation keys in English and German
- **Use-case context providers**: `UseCaseProvider` and `FeaturesProvider` wired into App.tsx

### Fixed
- **Setup wizard admin role bug**: `role` was missing from User model `$fillable` — setup wizard created admin with `role=user` instead of `role=admin`. All new installs affected.
- **AdminSettings use-case dropdown**: Options now match backend presets (company, residential, shared, rental, personal)

### Improved
- **Test coverage**: 67 PHP tests (160 assertions), 33 frontend vitest tests, all passing
- **Frontend synced** with Rust edition (identical `parkhub-web/` source)

---

## [1.3.0] - 2026-03-18

### Added
- **Demo auto-reset**: Scheduled auto-reset every 6 hours via Laravel scheduler when `DEMO_MODE=true`
- **Demo status tracking**: `GET /api/v1/demo/status` now returns `last_reset_at`, `next_scheduled_reset`, `reset_in_progress`
- **DemoOverlay countdown**: Frontend shows time since last reset, countdown to next auto-reset, and reset-in-progress indicator

### Fixed
- **GDPR export route**: Fixed broken `/users/me/export` route pointing to `exportData` instead of `export`
- **Swap race condition**: Wrapped slot swap in `DB::transaction()` with `lockForUpdate()` to prevent double-booking
- **Admin pagination**: Added pagination to admin bookings endpoint (prevent DOS via unbounded query)
- **Demo reset error handling**: Returns HTTP 500 on failed reset instead of silently swallowing exception
- **iCal import**: Added date validation and title truncation (prevents 500 on malformed iCal input)
- **Duplicate scheduling**: Removed duplicate `bookings:auto-release` (ran via both command and job every 5 min)

### Improved
- **Reset tracking**: `performReset()` now tracks `last_reset_at`, `next_scheduled_reset`, and `reset_in_progress` via Cache

---

## [1.2.0] - 2026-02-28

### Added
- **Missing admin routes wired**: `/admin/bookings`, `/admin/reports`, `/admin/dashboard-charts`, `/admin/branding`, `/admin/privacy`, `/admin/impressum`, `/admin/database/reset`, `/admin/auto-release`, `/admin/email-settings`, `/admin/users/export-csv`, `/admin/bookings/export-csv`
- **Waitlist routes**: `GET/POST /waitlist`, `DELETE /waitlist/{id}` now wired to WaitlistController
- **Single booking endpoint**: `GET /bookings/{id}` now accessible (was in controller but not in routes)
- **User endpoints**: `GET /user/export`, `GET /user/calendar-export`, `GET /absences/import-ical`, `GET /team/today`
- **Vehicle photo endpoints**: `GET/POST /vehicles/{id}/photo` now accessible
- **Announcements endpoint**: `GET /announcements/active` with proper `expires_at` null-safe filter
- **Queue worker**: New `worker` service in docker-compose.yml for async email/webhook processing
- **Scheduler service**: New `scheduler` service in docker-compose.yml for scheduled jobs
- **SendWebhookJob**: Webhooks are now actually delivered via a queued HTTP job with HMAC-SHA256 signing and 3 retries
- **AutoReleaseBookingsJob**: Scheduled every 5 minutes — auto-cancels bookings with no check-in after timeout
- **ExpandRecurringBookingsJob**: Daily at 01:00 — pre-creates bookings from recurring patterns for the next 7 days
- **Sanctum token pruning**: Expired tokens pruned on container start and daily via scheduler
- **Koyeb deployment**: Added `koyeb.yaml` for one-command Koyeb deployment

### Fixed
- **LIKE injection**: `auditLog()` search now escapes `%`, `_`, `\` characters before interpolation
- **N+1 query**: `PublicController::occupancy()` and `display()` now use single aggregation queries (was N+1 per lot)
- **QUEUE_CONNECTION**: Changed from `sync` to `database` so queued jobs actually queue

---

## [1.1.0] — 2026-02-28

### Security

- **Admin middleware**: Created `RequireAdmin` middleware — all 10 admin routes now protected at route
  level via `Route::middleware(['admin'])`. Previously only enforced via in-method checks.
- **Rate limiting on auth routes**: `POST /auth/forgot-password` and `POST /auth/reset-password` now
  limited to 5 requests per 15 minutes per IP (was unprotected).
- **Double-booking race condition**: `BookingController::store()` now wraps slot conflict check and
  `Booking::create()` in `DB::transaction()` with `ParkingSlot::lockForUpdate()`. Prevents
  concurrent requests from double-booking the same slot.
- **Booking status IDOR**: `BookingController::update()` no longer accepts `status` from user input —
  only `notes` and `vehicle_plate` are updatable by users.
- **Sanctum token expiry**: Changed `expiration` from `null` (never) to `10080` minutes (7 days).

### Fixed

- **GDPR Art. 17 erasure**: `UserController::anonymizeAccount()` now fully anonymizes all data:
  vehicle photos deleted from storage, audit log entries anonymized (IP → `0.0.0.0`),
  guest bookings anonymized, user preferences cleared.
- **Recurring booking validation**: `RecurringBookingController` now validates `start_date ≥ today`,
  `end_date > start_date`, and `end_time > start_time`.

### Added

- `POST /auth/change-password` — change password (requires current password, rotates token)
- `POST /auth/refresh` — refresh Sanctum token (revoke all + reissue)
- `GET /legal/privacy` and `GET /legal/impressum` — public legal/transparency pages

---

## [1.0.1] — 2026-02-27

### Fixed

- **Security**: `Setup.tsx` auto-login bypass — the setup page previously attempted an
  automatic `admin`/`admin` login for any unauthenticated visitor when `needs_password_change`
  was set. Now auto-login only occurs when no admin account exists yet (genuine first install).
  If an admin already exists, the user is redirected to the normal login page.
- **Frontend**: Bookings page showed 0 items despite the counter displaying the correct total.
  Backend creates bookings with `status: 'confirmed'` but the filter only matched
  `status: 'active'`. Both `confirmed` and `active` now display in the Active/Upcoming sections.
- **Frontend**: Admin Privacy settings tab crashed with "Failed to load privacy settings" due
  to a template literal syntax error — `(import.meta.env.VITE_API_URL || "")` (parentheses)
  was used instead of `${import.meta.env.VITE_API_URL || ""}` (template expression), producing
  a malformed URL that always returned 404.
- **Frontend**: LicensePlateInput component truncated plates to 3 characters when a full plate
  string (e.g. `M-AB 1234`) was typed or pasted at once before selecting a city from the
  dropdown. The component now auto-detects and formats the full plate on input.

---

## [1.0.0] — 2026-02-27

### Added

**Core infrastructure**
- Laravel 12 + Sanctum API backend with PHP 8.3
- React 19 + TypeScript + Tailwind CSS frontend (SPA)
- SQLite support for zero-dependency development and small deployments
- MySQL 8 support for production deployments
- Docker image: PHP 8.3 + Apache, multi-stage build with Node 20 frontend compile
- Docker Compose configuration with MySQL 8 and named volumes
- `docker-entrypoint.sh`: auto-migration, default admin creation, config caching on startup

**Authentication**
- Laravel Sanctum Bearer token authentication with 7-day token expiry
- Login by username or email
- Registration with input validation (alpha_dash username, unique email, min 8-char password)
- Token refresh (revoke all + reissue)
- Forgot password endpoint (rate limited 5/15min, prevents user enumeration)
- Change password endpoint (requires current password, rotates token)
- Account deletion with password confirmation (CASCADE)
- Rate limiting: 10 requests/minute on login and register endpoints

**Parking lots**
- Full CRUD for parking lots (name, address, total_slots, status, layout JSON)
- Auto-generated slot layout from slot records (rows of 10) when no layout is stored
- Real-time available slot count via optimized single-query occupancy calculation
- Lot occupancy endpoint (total, occupied, available, percentage)
- QR code endpoint for lot and individual slot (links to quick booking URL)

**Zones**
- Full CRUD for zones within lots (name, color, description)
- Slots assignable to zones; zone deletion sets zone_id to null on slots

**Parking slots**
- Full CRUD for slots (slot_number, status, zone_id, reserved_for_department)
- Current booking status included in slot list response

**Bookings**
- Create booking with optional auto-slot assignment
- Conflict detection (overlapping bookings on same slot rejected with 409)
- Quick booking (auto-assign best available slot)
- Guest booking (named guest, unique guest code, no user account needed)
- Booking swap request workflow (create request, accept/reject)
- Check-in endpoint
- Update booking notes
- Cancel booking (delete)
- Filter bookings by status, from_date, to_date
- Booking confirmation email on creation (queued via `BookingConfirmation` Mailable)
- HTML invoice endpoint (printer-friendly, browser Print → PDF)
- Calendar events endpoint for calendar view
- Audit log entries for booking create/delete

**Recurring bookings**
- Create recurring patterns (days_of_week array, date range, start/end time)
- Full CRUD for recurring patterns

**Absences**
- Full CRUD for absences (homeoffice, vacation, sick, training, other)
- Absence patterns (recurring weekly homeoffice)
- Team absences view (all users' absences for planning)
- iCal import (from .ics file upload)
- Legacy `homeoffice` and `vacation` endpoints for Rust-frontend compatibility

**Vehicles**
- Full CRUD for user vehicles (plate, make, model, color, is_default)
- Vehicle photo upload (multipart or base64, max 5/8 MB, GD validation and resize to 800px)
- Vehicle photo serve endpoint
- 400+ German Kfz-Unterscheidungszeichen (city code lookup)
- Photo deletion on vehicle delete

**User features**
- User preferences (theme, language, timezone, notifications, default lot, locale)
- User statistics (total bookings, this month, homeoffice days, favourite slot)
- Favourite slots (add, list, remove)
- In-app notifications (list last 50, mark read, mark all read)
- iCal calendar export (bookings as .ics feed)
- Web Push notification subscription / unsubscription
- Webhooks (CRUD per user, plus admin-wide webhook settings)
- QR code endpoint per booking (`/api/qr/{bookingId}`)

**Team**
- Team list endpoint (all active users)
- Team today endpoint (office / homeoffice / vacation status)

**Waitlist**
- Full CRUD for waitlist entries

**Admin**
- Admin dashboard: total users, lots, slots, bookings, active bookings, occupancy %, homeoffice today, bookings today
- Booking heatmap (day of week × hour, DB-agnostic: SQLite `strftime` / MySQL `DAYOFWEEK`)
- Booking reports (by day, status, booking_type, average duration)
- Dashboard chart data (booking trend, current occupancy)
- Paginated, searchable, filterable audit log
- Announcements CRUD (info / warning / error / success, expiry support)
- User management: list, update (name, email, role, is_active, department, password), delete
- Bulk user import (up to 500 users via JSON, skips existing)
- CSV export of all bookings
- Application settings (company name, use case, registration mode, licence plate mode, guest bookings, auto-release, branding colors)
- Email settings (SMTP, from address — stored in settings table, used to update `.env` equivalent)
- Auto-release settings (enabled/disabled, timeout minutes)
- Webhook settings (admin-wide webhook list)
- Branding: company name, primary color, logo upload (2 MB max), default SVG logo fallback
- Privacy / GDPR settings (policy text, retention days, gdpr_enabled flag)
- Impressum editor (all DDG §5 fields: provider name, legal form, address, email, phone, register, VAT ID, responsible person, custom text)
- Database reset endpoint (requires `confirm: "RESET"`, deletes all data except calling admin)
- Slot update and lot delete admin variants

**GDPR**
- Art. 20 data export: full JSON of user's profile, bookings, absences, vehicles, preferences
- Art. 17 anonymization: strips all PII, keeps booking records with `[GELÖSCHT]` plate, sets account inactive
- Audit log entry for GDPR erasure (stores anonymized ID and reason, not original PII)
- Legal templates: `legal/impressum-template.md`, `legal/datenschutz-template.md`, `legal/agb-template.md`, `legal/avv-template.md`

**Public / unauthenticated endpoints**
- Real-time lot occupancy (for lobby displays)
- Active announcements
- Public branding (company name, colors, logo)
- Public Impressum (DDG §5 — legally required to be unauthenticated)
- Health endpoints: `/health/live`, `/health/ready`
- System version and maintenance status

**Database**
- 18 tables: users, parking_lots, zones, parking_slots, bookings, vehicles, absences, settings, audit_log, announcements, notifications_custom, favorites, recurring_bookings, guest_bookings, booking_notes, push_subscriptions, webhooks, waitlist_entries
- UUID primary keys on all domain tables
- Indexes on high-query columns (slot/time overlap, user/status, action/created_at)
- Foreign key constraints with appropriate cascade / set-null behaviour

**Email notifications**
- `WelcomeEmail` Mailable: sent on registration (queued)
- `BookingConfirmation` Mailable: sent on booking creation (queued)
- Queue driver: `database` by default; falls back gracefully if no worker is running

**Deployment**
- `Dockerfile`: multi-stage build (Node 20 frontend + PHP 8.3 Apache backend)
- `docker-compose.yml`: app + MySQL 8 with health check and named volume
- `docker-entrypoint.sh`: auto-migrate, create default admin, cache config/routes
- `deploy-shared-hosting.sh`: builds a zip for shared hosting upload
- `docs/DOCKER.md`, `docs/VPS.md`, `docs/SHARED-HOSTING.md`, `docs/PAAS.md`
- `shell.nix`: Nix development environment

**Frontend pages (React 19 + TypeScript + Tailwind)**
- Login, Register, ForgotPassword, Setup
- Dashboard, Book, Bookings, Calendar
- Vehicles, Team, Absences, Homeoffice, Vacation
- Profile, Admin, AdminBranding, AdminImpress, AdminPrivacy, AuditLog
- Impressum, Privacy, Terms, Legal, Help, About, Welcome
- OccupancyDisplay (unauthenticated lobby display)

**API compatibility**
- `/api/v1/*` routes mirror the ParkHub Docker (Rust edition) endpoint structure
- Legacy `/api/*` routes for backwards compatibility
- `GET /api/v1/admin/updates/check` stub (returns `update_available: false`)

**Demo data**
- `ProductionSimulationSeeder`: 10 German parking lots (München, Stuttgart, Köln, Frankfurt, Hamburg, Nürnberg, Karlsruhe, Heidelberg, Dortmund, Leipzig), 200 German-named users across 10 departments, ~3500 bookings over 30 days

---

## [1.1.1] — 2026-02-28

### Critical Bug Fixes

- **`/api/v1/health/ready` HTTP 500**: Readiness probe now gracefully falls back to `1.0.0-php` when `VERSION` file is absent in container
- **`/api/v1/announcements/active` HTTP 500**: Removed filter on non-existent `expires_at` column; returns all active announcements
- **`password_confirmation` ignored on registration**: Added `confirmed` validation rule — password and password_confirmation must now match
- **Past booking accepted**: `POST /api/v1/bookings` now rejects `start_time` in the past with HTTP 422 `INVALID_BOOKING_TIME`
- **Profile save lost on refresh**: `Profile.tsx handleSave()` now calls `PUT /api/v1/users/me` — profile changes persist
- **Vehicle creation always failed**: `api/client.ts` was sending `license_plate` but backend requires `plate` — corrected
- **New lots had 0 slots**: `LotController::store()` now auto-generates `total_slots` slot records (001..N) on lot creation

### Security

- **Apache security headers**: `X-Content-Type-Options`, `X-Frame-Options`, `Content-Security-Policy`, `HSTS`, `Referrer-Policy`, `Permissions-Policy` added via `.htaccess`
- **Server/framework version hidden**: `X-Powered-By: PHP` and `Server: Apache` response headers suppressed

### Other Improvements

- Added `GET /api/v1/bookings/{id}` single-booking detail endpoint
- GDPR account deletion response now returns `{"success":true}` instead of `Unauthenticated` error
- CORS restricted from wildcard `*` to specific allowed origins
- Admin users list now paginated (default 20, max 100 per page)
- Login page: removed duplicate theme toggle button
- Router: `/datenschutz` redirects to `/privacy`, `/agb` redirects to `/terms`
- Removed stale `.backup` development files from production image
