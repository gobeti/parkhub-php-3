# ParkHub PHP — Deep Dive Audit Report

**Date:** 2026-03-21
**Auditor:** Elly (Claude Code, TAB-2)
**Repo:** nash87/parkhub-php
**Branch:** main (HEAD: 6386981)
**Version:** 1.9.x (Laravel 12, PHP 8.2+)

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Feature Inventory](#feature-inventory)
3. [Security Audit](#security-audit)
4. [Code Review](#code-review)
5. [Tech Debt Analysis](#tech-debt-analysis)
6. [Testing Strategy](#testing-strategy)
7. [CI/CD Review](#cicd-review)
8. [Documentation](#documentation)
9. [Priority Issue List](#priority-issue-list)

---

## Executive Summary

ParkHub PHP is a mature, well-structured Laravel 12 self-hosted parking management system. Overall quality is **above average** for an open-source project. Security posture is solid, CI/CD is functional, and test coverage is broad. The main gaps are:

| Area | Status | Rating |
|------|--------|--------|
| Security | Good. Few medium issues. | ★★★★☆ |
| Code Quality | Good. Some structural debt. | ★★★★☆ |
| Testing | Broad but shallow unit coverage. No phpstan. | ★★★☆☆ |
| CI/CD | Functional. Missing static analysis + security scan. | ★★★☆☆ |
| Documentation | Excellent. Most comprehensive in class. | ★★★★★ |

---

## Feature Inventory

### Core Features (Production-Ready)

| Feature | Implementation | Tests |
|---------|---------------|-------|
| Auth (login/register/password reset) | AuthController, Sanctum tokens, rate-limited | ✅ Full |
| Parking Lots (CRUD) | LotController, per-lot pricing | ✅ Full |
| Zones (per-lot) | ZoneController | ✅ |
| Slots (per-lot, with types/features) | SlotController, features JSON column | ✅ Full |
| Standard Bookings | BookingController | ✅ Full |
| Quick Book (auto-assign slot) | BookingController::quickBook | ✅ |
| Guest Bookings | BookingController::guestBooking | ✅ |
| Booking Swap | BookingController::swap | ✅ |
| Recurring Bookings | RecurringBookingController, expansion job | ✅ Full |
| Absences | AbsenceController | ✅ Full |
| iCal Import (absences) | AbsenceController::importIcal | ✅ |
| iCal Export (calendar) | UserController::calendarExport | ✅ |
| Vehicles (CRUD) | VehicleController | ✅ |
| Vehicle Photo Upload | VehicleController::uploadPhoto | ✅ |
| Waitlist | WaitlistController, notification on slot open | ✅ |
| Credits System | AdminCreditController, monthly quota | ✅ Full |
| Team View | TeamController | ✅ |
| User Favorites | UserController::favorites | ✅ |
| Notifications (push + in-app) | PushNotificationService, VAPID | ✅ |
| Webhooks | MiscController::webhooks | ✅ |
| Announcements | AdminAnnouncementController, expiry | ✅ |
| Admin User Management | AdminController | ✅ Full |
| Bulk User Import (CSV/JSON) | AdminController::importUsers | ✅ |
| Admin Bookings | AdminController::bookings | ✅ |
| Admin Reports & Heatmap | AdminReportController | ✅ |
| CSV Export (bookings/users) | AdminReportController | ✅ |
| Admin Settings | AdminSettingsController | ✅ |
| Branding / Logo Upload | AdminSettingsController | ✅ |
| GDPR Export & Delete | GdprTest, UserController | ✅ |
| Audit Log | AuditLog model, logged throughout | ✅ |
| Email Notifications | BookingConfirmation, Reminder, Welcome, WaitlistSlotAvailable, PasswordReset | ✅ |
| PWA | parkhub-web (React 19 + Vite) | ✅ |
| Public Occupancy Display | PublicController | ✅ |
| Prometheus Metrics | MetricsController | ✅ |
| Per-Lot Pricing | ParkingLot: hourly_rate, daily_max, monthly_pass, currency | ✅ |
| Slot Types/Features | parking_slots.features JSON | ✅ |
| PDF Invoice (HTML print) | BookingInvoiceController | ✅ |
| QR Code (booking) | MiscController::qrCode | ✅ |
| Recommendation Engine | RecommendationController (score-based) | ✅ |
| Translation System | TranslationController, proposals, votes | ✅ |
| Setup Wizard | SetupController | ✅ |
| Demo Mode | Docker entrypoint seeding | ✅ |
| Pulse Monitoring | PulseController | ✅ |
| Multi-locale (10+ languages) | parkhub-web i18n | ✅ |
| Dark Mode | parkhub-web | ✅ |
| PostgreSQL Support | Added in v1.9.x | ✅ |

### Planned / Partial Features

| Feature | Status | Notes |
|---------|--------|-------|
| German License Plate OCR | Partial | Photo upload exists; OCR backend not implemented |
| OTA Updates | Not in this repo | Separate `ota.securanido.com` |

---

## Security Audit

### Authentication & Authorization

**Sanctum Token Auth** — Well configured:
- Tokens expire after 7 days (`expiration: 10080` in `config/sanctum.php`)
- `throttle:auth` (5/min) on login/register — good brute-force protection
- `throttle:password-reset` (3/15min) — correct
- Password reset uses hashed tokens (not plaintext), 60-minute expiry
- Generic response on forgot-password prevents user enumeration ✅
- Password requires uppercase + lowercase + digit + 8+ chars ✅
- All sessions invalidated on password change/reset ✅

**Admin Authorization** — Two-layer protection:
- Route-level: `Route::middleware(['admin'])` via `RequireAdmin` middleware
- Controller-level: `$this->requireAdmin($request)` redundantly in AdminController methods
- This is fine (defense in depth), but the controller-level checks are redundant given the middleware

**Issue — Double `requireAdmin` in AdminController**: The `admin` middleware is applied to the entire admin route group, so the `private function requireAdmin()` inside AdminController is dead code. Not a vulnerability, but confusing.

### Input Validation

All endpoints validate via `$request->validate()` — Laravel's built-in validator (uses parameterized queries automatically). No raw input in SQL.

**Raw SQL usage review:**
```
LotController: selectRaw('lot_id, COUNT(*) as total') — safe, no user input
AdminReportController: selectRaw('CAST(strftime...)') — safe, hardcoded
PublicController: DB::raw('COUNT(*) as occupied') — safe
```
No SQL injection vectors found. ✅

**CSV Injection Protection:**
`csvSafe()` in AdminReportController prefixes values starting with `=`, `+`, `-`, `@`, tab, CR with a single quote — standard protection. ✅

### Security Headers

`SecurityHeaders` middleware applied globally. Headers set:

| Header | Value | Assessment |
|--------|-------|------------|
| X-Content-Type-Options | nosniff | ✅ |
| X-Frame-Options | DENY | ✅ |
| X-XSS-Protection | 1; mode=block | ✅ (legacy, fine) |
| Referrer-Policy | strict-origin-when-cross-origin | ✅ |
| Permissions-Policy | restrictive | ✅ |
| HSTS | opt-in via APP_HSTS | ✅ |
| CSP | script-src 'self', style-src 'self' **'unsafe-inline'** | ⚠️ |

**Issue — CSP `style-src 'unsafe-inline'`**: Allows inline styles. While the SPA uses bundled CSS, this weakens CSP. Could be tightened with a nonce or hash approach, or by eliminating inline styles.

### CORS

Explicit allowed origins (not wildcard). Explicit allowed methods and headers. Allows GitHub Pages pattern for demo. ✅

**Minor**: `allowed_origins` includes `APP_URL` which comes from `env()`. In testing with in-memory SQLite, this would be `null` — `array_filter` handles it. ✅

### File Upload Security

**Vehicle photos** (`VehicleController::uploadPhoto`):
- Validates `mimes:jpeg,png,gif,webp|max:5120` ✅
- Accepts base64 alternative — uses `base64_decode` with strict mode
- Stores to `local` disk (not public) — served via `servePhoto` action, ownership-checked ✅
- File extension forced to `.jpg` regardless of upload — good

**Branding logo** (`AdminSettingsController::uploadBrandingLogo`):
- Validates `image|max:2048` ✅
- Stores to `public` disk (served directly) ✅

### Prometheus Metrics Endpoint

`GET /api/metrics` — No auth by default. When `METRICS_TOKEN` is set in config, bearer token is required. Leaks: total users count, booking counts, occupancy. **Medium risk** in untrusted networks. Recommend requiring token by default.

### Public Endpoints

`GET /api/public/occupancy` and `GET /api/public/display` — Expose lot names, occupancy data. Intentional (for lobby displays). No PII. ✅

### iCal Import

Regex parser, max 1MB input, no external library. The regex `BEGIN:VEVENT(.*?)END:VEVENT` with `/s` flag handles multiline. No XXE (not XML). Summaries truncated to 255 chars. ✅

Minor: Very large iCal files could be slow — no processing time limit beyond the 1MB size cap.

### Password Reset Token Storage

Stores `Hash::make($token)` in `password_reset_tokens` — not plaintext. 60-minute expiry. Deleted after use. ✅

### Missing: No Account Lockout

After failed login, only rate limiting (5 attempts/min) prevents brute force. No per-account lockout (`is_active = false` after N failures). With rate limiting this is acceptable, but an optional lockout policy would strengthen this.

---

## Code Review

### Architecture

Clean Laravel 12 structure: Controllers → Resources → Models. Middleware layering is correct. Jobs for async operations (email, webhooks, stats). Services for push notifications.

### Booking Race Condition

`BookingController::store` checks slot availability with a `where` query, then creates — no distributed lock or database-level unique constraint prevents double-booking in concurrent requests.

```php
// BookingController.php — vulnerability window between check and insert
$conflict = Booking::where('slot_id', $slotId)...->exists();
if ($conflict) return 409;
// ← another request could book the same slot here
$booking = Booking::create([...]);
```

**Fix**: Wrap in `DB::transaction()` with a `SELECT FOR UPDATE` or add a unique index on `(slot_id, start_time, end_time)` to make the duplicate fail at DB level.

### `available_slots` Denormalization

`ParkingLot::available_slots` is a persisted column but recalculated on every `LotController::index` call (correctly). However, `MetricsController` reads it directly from the DB column, which may be stale:

```php
// MetricsController — reads stale available_slots
$occupied = $total - $lot->available_slots;
```

This is a consistency issue: the column isn't auto-updated on booking creation. The `LotController::index` recalculates correctly, but other paths don't.

### AdminController `requireAdmin` Redundancy

The `admin` middleware is applied in routes, but `AdminController` also has a private `requireAdmin()` method called in every action. This is dead code — the middleware already rejects non-admins before the controller runs. Not a bug, but dead code that misleads reviewers.

### Booking `store` — No End-Time Default

If `end_time` is `null`, the booking is created without an end time. The validation says `nullable`. Some downstream code (`BookingInvoiceController`) does `$end = $booking->end_time ? ... : $start + 3600` — so there's a fallback, but an open-ended booking is conceptually odd for a parking system.

### `RecommendationController` — N+1 Hidden

```php
foreach ($lots as $lot) {
    foreach ($lot->slots as $slot) { // slots eager-loaded ✅ but...
        $features = $slot->features ?? []; // JSON column cast — OK
```
Lots are eager-loaded with slots. OK.

### Translation System

`TranslationController`, `TranslationProposal`, `TranslationVote` models exist. Community translation voting is an interesting addition but adds significant complexity with limited test coverage.

### Hardcoded `mailto:admin@parkhub.test`

```php
// PushNotificationService.php
'subject' => 'mailto:admin@parkhub.test',
```

VAPID subject should use the configured admin email, not a hardcoded test address. This could cause push notification delivery issues in production.

---

## Tech Debt Analysis

### Priority: High

| # | Issue | Impact | Effort |
|---|-------|--------|--------|
| TD-1 | No phpstan/larastan — no static analysis | Bugs slip to runtime | Low |
| TD-2 | Booking race condition (no transaction/lock) | Double-booking possible | Medium |
| TD-3 | `MetricsController` reads stale `available_slots` | Inaccurate Prometheus metrics | Low |
| TD-4 | No composer audit in CI | Known CVEs undetected | Low |
| TD-5 | VAPID subject hardcoded to test email | Push failures in production | Low |

### Priority: Medium

| # | Issue | Impact | Effort |
|---|-------|--------|--------|
| TD-6 | `AdminController::requireAdmin()` private method is dead code | Confusion in code review | Low |
| TD-7 | Prometheus metrics endpoint unprotected by default | Data leakage | Low |
| TD-8 | CSP `style-src 'unsafe-inline'` | Weak CSP | Medium |
| TD-9 | Only 1 unit test (ExampleTest) — no unit coverage for services/models | No isolated test signal | High |
| TD-10 | No soft deletes on Booking/User | Can't restore accidentally deleted data | Medium |
| TD-11 | `BookingController` too large (~800 lines) | Maintenance burden | High |
| TD-12 | DB-specific raw SQL (SQLite strftime vs MySQL DAYOFWEEK) | Fragile dual-DB support | Medium |
| TD-13 | No account lockout (only rate limiting) | Brute force under distributed IPs | Medium |

### Priority: Low

| # | Issue | Impact | Effort |
|---|-------|--------|--------|
| TD-14 | No `sanctum:prune-expired` scheduled command | Token table grows indefinitely | Low |
| TD-15 | Booking `end_time` nullable — semantic ambiguity | Edge cases | Low |
| TD-16 | `TranslationController` untested | New feature without coverage | Medium |
| TD-17 | No OpenAPI/Swagger spec | API consumers need manual reading | High |
| TD-18 | `health` endpoint returns hardcoded `version: 1.3.0` | Stale version string | Low |
| TD-19 | Import users: N queries in loop (1 INSERT + 1 EXISTS per user) | Slow at scale | Medium |

---

## Testing Strategy

### Current State

| Metric | Value |
|--------|-------|
| Total tests | 484 |
| Feature tests | 48 files |
| Unit tests | 1 file (ExampleTest only) |
| Test runner | PHPUnit 11 |
| Coverage reporting | None configured |
| Static analysis | None (no phpstan/larastan) |
| Database | SQLite in-memory |
| E2E | Maestro YAML (5 flows) |

### Test Quality Assessment

**Strengths:**
- Broad feature coverage: all major controllers have dedicated test files
- Edge case files exist: `AuthEdgeCaseTest`, `BookingEdgeCaseExtendedTest`, etc.
- Security headers tested explicitly (`SecurityHeadersTest`)
- GDPR flows tested
- Job queue tested (`JobQueueTest` — 510 lines)
- Rate limiting scenarios covered
- Pagination tested

**Weaknesses:**
1. **Zero unit tests for services/models**: `PushNotificationService`, `AuditLog`, models with business logic — none tested in isolation
2. **No coverage report**: Can't track which lines are actually covered
3. **Only SQLite**: PostgreSQL-specific SQL (DAYOFWEEK, HOUR functions) never tested in CI
4. **Missing scenarios**:
   - Concurrent booking (race condition not testable without real DB)
   - VAPID key generation command
   - RecurringBookingExpansion job edge cases
   - Translation voting logic
   - Credit edge cases across month boundaries
5. **No Pest**: PHPUnit 11 works, but Pest would enable data providers and cleaner test organization
6. **No mutation testing**: Infection PHP would identify untested branches

### Recommendations

1. Add larastan (Level 5+) to CI
2. Enable coverage (`--coverage-text`) in CI with `xdebug` or `pcov`
3. Add PostgreSQL test job (separate GH Actions job)
4. Expand `tests/Unit/` with at minimum: `UserTest`, `BookingTest`, `PushNotificationServiceTest`
5. Add `infection/infection` for mutation score tracking

---

## CI/CD Review

### Existing Workflows

#### `ci.yml`
| Job | Status | Notes |
|-----|--------|-------|
| PHP Tests | ✅ Works | SQLite only |
| PHP Lint (Pint) | ✅ Works | Style enforced |
| Frontend Build & Test | ✅ Works | Vitest |
| Gate job `ci` | ✅ Correct | Branch protection target |

**Missing from CI:**
- `composer audit` — no CVE scanning
- `phpstan`/`larastan` — no static analysis
- PHP 8.4 vs 8.2 matrix — CI runs 8.4, minimum is 8.2 (untested combination)
- PostgreSQL test run
- Coverage report
- SAST (e.g. Psalm, Semgrep)

#### `docker-publish.yml`
| Feature | Status | Notes |
|---------|--------|-------|
| GHCR push on tag | ✅ | Semver tags |
| Build cache via registry | ✅ | Efficient |
| Multi-platform | ⚠️ | linux/amd64 only, no arm64 |
| Render deploy | ✅ | Auto-deploy on tag/main |
| Env var injection via Render API | ⚠️ | Demo credentials in workflow (acceptable for demo) |

### Recommended CI Addition

```yaml
# Add to ci.yml jobs:
static-analysis:
  name: Static Analysis (Larastan)
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4
    - uses: shivammathur/setup-php@v2
      with: { php-version: '8.4', tools: composer:v2 }
    - run: composer install --prefer-dist --no-interaction --no-progress
    - run: ./vendor/bin/phpstan analyse --memory-limit=512M

security:
  name: Security Audit
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4
    - uses: shivammathur/setup-php@v2
      with: { php-version: '8.4', tools: composer:v2 }
    - run: composer install --prefer-dist --no-interaction --no-progress
    - run: composer audit
```

---

## Documentation

| Document | Status | Quality |
|----------|--------|---------|
| README.md | ✅ Redesigned | Professional, badges, screenshots |
| ARCHITECTURE.md | ✅ Present | Detailed tech overview |
| CHANGELOG.md | ✅ Present | Well-maintained |
| docs/API.md | ✅ Present | Comprehensive REST reference |
| docs/CONFIGURATION.md | ✅ | All env vars documented |
| docs/DOCKER.md | ✅ | Detailed Docker Compose guide |
| docs/INSTALLATION.md | ✅ | Multiple install paths |
| docs/GDPR.md | ✅ | GDPR compliance guide |
| docs/SECURITY.md | ✅ | Security policy |
| docs/PAAS.md | ✅ | Render/Koyeb guide |
| docs/SHARED-HOSTING.md | ✅ | Shared hosting support |
| SECURITY-AUDIT.md | ✅ | Self-audit (older) |
| COMPLIANCE-REPORT.md | ✅ | Compliance tracking |
| **OpenAPI/Swagger spec** | ❌ Missing | Needed for SDK generation |
| **CONTRIBUTING.md** | ✅ Present | |

**Gap**: No OpenAPI 3.x spec (`openapi.yaml`). The API.md is thorough but machine-unreadable. Adding an OpenAPI spec would enable automatic SDK generation and interactive docs.

---

## Priority Issue List

### P0 — Critical

| ID | Title | Label |
|----|-------|-------|
| SEC-1 | Booking race condition: concurrent requests can double-book same slot | `bug`, `security`, `P0` |

### P1 — High

| ID | Title | Label |
|----|-------|-------|
| CI-1 | Add larastan to CI pipeline | `ci`, `dx`, `P1` |
| CI-2 | Add `composer audit` security scan to CI | `ci`, `security`, `P1` |
| BUG-1 | VAPID subject hardcoded to `admin@parkhub.test` — push fails in production | `bug`, `P1` |
| BUG-2 | MetricsController reads stale `available_slots` column | `bug`, `P1` |

### P2 — Medium

| ID | Title | Label |
|----|-------|-------|
| TD-1 | Remove redundant `requireAdmin()` in AdminController | `tech-debt`, `P2` |
| TD-2 | Prometheus metrics should require token by default | `security`, `P2` |
| TD-3 | Add Sanctum token pruning to scheduled commands | `tech-debt`, `P2` |
| TD-4 | Expand unit tests: UserModel, BookingModel, PushNotificationService | `testing`, `P2` |
| TD-5 | Add PostgreSQL CI job | `ci`, `testing`, `P2` |
| TD-6 | Health endpoint returns hardcoded version `1.3.0` | `bug`, `P2` |
| TD-7 | Tighten CSP: remove `style-src 'unsafe-inline'` | `security`, `P2` |
| TD-8 | Add soft deletes to Booking and User models | `tech-debt`, `P2` |

### P3 — Low / Enhancement

| ID | Title | Label |
|----|-------|-------|
| ENH-1 | Generate OpenAPI 3.x spec | `documentation`, `enhancement`, `P3` |
| ENH-2 | Add arm64 to Docker multi-platform build | `ci`, `enhancement`, `P3` |
| ENH-3 | Add optional account lockout after N failed logins | `security`, `enhancement`, `P3` |
| ENH-4 | Split BookingController into smaller focused classes | `tech-debt`, `refactor`, `P3` |
| ENH-5 | Add Pest migration / upgrade from PHPUnit | `testing`, `enhancement`, `P3` |
| ENH-6 | Add coverage reporting (pcov) to CI | `testing`, `ci`, `P3` |
| ENH-7 | Optimize bulk user import: batch INSERT instead of loop | `performance`, `P3` |
| ENH-8 | Implement German license plate OCR backend | `feature`, `P3` |
| ENH-9 | Add `booking:end_time required` validation or default duration | `enhancement`, `P3` |
| ENH-10 | Add mutation testing with Infection PHP | `testing`, `enhancement`, `P3` |

---

## Appendix: Dependency Review

```json
// composer.json require:
"laravel/framework": "^12.54"   // Latest — ✅
"laravel/sanctum": "^4.3"       // Latest — ✅
"minishlink/web-push": "^10.0"  // Latest stable — ✅

// require-dev:
"phpunit/phpunit": "^11.5.50"   // Latest — ✅
"laravel/pint": "^1.24"         // Latest — ✅
// Missing: nunomaduro/larastan, phpstan/phpstan
```

No `composer audit` results available (not in CI). Recommend running locally and in CI.

---

*Report generated by Elly — automated audit via code analysis. All issues have been filed as GitHub Issues on nash87/parkhub-php.*
