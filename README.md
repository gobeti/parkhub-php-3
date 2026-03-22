<p align="center">
  <img src="public/favicon.svg" alt="ParkHub PHP" width="96">
</p>

<h1 align="center">ParkHub PHP — Self-Hosted Parking Management</h1>

<p align="center">
  <a href="https://github.com/nash87/parkhub-php/actions/workflows/ci.yml"><img src="https://github.com/nash87/parkhub-php/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="CHANGELOG.md"><img src="https://img.shields.io/badge/Release-v2.5.0-brightgreen.svg?style=flat-square" alt="v2.5.0"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square" alt="MIT License"></a>
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.4-777BB4.svg?style=flat-square&logo=php&logoColor=white" alt="PHP 8.4"></a>
  <a href="https://laravel.com/"><img src="https://img.shields.io/badge/Laravel-12-FF2D20.svg?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 12"></a>
  <a href="https://react.dev/"><img src="https://img.shields.io/badge/React-19-61DAFB.svg?style=flat-square&logo=react&logoColor=black" alt="React 19"></a>
  <img src="https://img.shields.io/badge/Tests-1300%2B-success.svg?style=flat-square" alt="1300+ tests">
  <a href="docs/GDPR.md"><img src="https://img.shields.io/badge/DSGVO-konform-green.svg?style=flat-square" alt="GDPR Compliant"></a>
  <a href="COMPLIANCE-REPORT.md"><img src="https://img.shields.io/badge/Compliance-Audited-brightgreen.svg?style=flat-square" alt="Compliance Audited"></a>
  <a href="docker-compose.yml"><img src="https://img.shields.io/badge/Docker-ready-2496ED.svg?style=flat-square&logo=docker&logoColor=white" alt="Docker Ready"></a>
</p>

<p align="center">
  <strong>Ihre Daten. Ihr Server. Ihre Kontrolle.</strong><br>
  The on-premise parking management platform that runs anywhere -- shared hosting, VPS, Docker, or Kubernetes.<br>
  Built with Laravel 12 and React 19. Zero cloud. Zero tracking. 100% GDPR compliant by design.
</p>

<p align="center">
  <a href="https://parkhub-php-demo.onrender.com"><strong>Try the Live Demo</strong></a> &nbsp;·&nbsp;
  <a href="docs/INSTALLATION.md">Installation</a> &nbsp;·&nbsp;
  <a href="docs/API.md">API Docs</a> &nbsp;·&nbsp;
  <a href="docs/GDPR.md">GDPR Guide</a> &nbsp;·&nbsp;
  <a href="CHANGELOG.md">Changelog</a> &nbsp;·&nbsp;
  <a href="SECURITY.md">Security</a>
</p>

---

## Why Self-Hosted?

Most parking management SaaS costs 200--2,000 EUR/month, stores your data on US cloud infrastructure, and requires a data processing agreement just to get started.

ParkHub is different. It runs on your server -- a shared hosting plan, a VPS, or your company network. Your data never leaves your premises, which means **no GDPR processor agreement needed**, no CLOUD Act exposure, and no monthly fees. The entire source code is MIT-licensed and auditable.

---

## Quick Start

### Docker (recommended)

```bash
git clone https://github.com/nash87/parkhub-php.git && cd parkhub-php
docker compose up -d
# Open http://localhost:8080 — Login: admin@parkhub.test / demo
```

The first build takes 2--5 minutes (installs Composer + Node dependencies, builds the React frontend). After that, starts are instant. Custom credentials from the start:

```bash
PARKHUB_ADMIN_EMAIL=you@company.com PARKHUB_ADMIN_PASSWORD=secure docker compose up -d
```

### Shared hosting

ParkHub PHP runs on any 3 EUR/month shared hosting with PHP 8.2+ and MySQL. Upload via FTP, open `install.php` in your browser, done. See [Installation Guide](docs/INSTALLATION.md).

### Artisan commands

```bash
php artisan serve                      # Dev server
php artisan test                       # Run 868 PHPUnit tests
php artisan migrate --seed             # Setup database
php artisan sanctum:prune-expired      # Clean expired tokens
php artisan schedule:run               # Run scheduled jobs
php artisan queue:work                 # Process background jobs
```

**[Live Demo](https://parkhub-php-demo.onrender.com)** | Login: `admin@parkhub.test` / `demo` | (auto-resets every 6 hours)

---

## Features

### v2.5.0 Highlights

- **6 switchable themes** -- Classic, Glass, Bento, Brutalist, Neon, Warm with instant CSS-variable switching
- **httpOnly cookie auth** -- XSS-proof authentication with SameSite=Lax and CSRF protection
- **Glass morphism UI** -- Bento grid dashboard with animated counters and frosted-glass cards
- **2FA/TOTP authentication** -- QR code enrollment, backup codes, per-account enable/disable
- **23 Laravel modules** -- Runtime-toggleable via `MODULE_*` env vars (see [Module System](#module-system))
- **Lighthouse CI** -- Automated accessibility (>= 95), performance (>= 90), SEO (>= 95) gates
- **Smart recommendations** -- Heuristic scoring engine that learns from usage patterns
- **Community translations** -- 10 languages with proposal voting and admin review

### Core

- **Full booking lifecycle** -- One-tap quick booking, recurring reservations, guest bookings, swap requests, waitlists, automatic no-show release, QR code check-in
- **Automatic pricing** -- Hourly rate x duration, 19% German VAT, daily max cap, monthly passes
- **Visual lot editor** -- Per-lot zones, slot types (standard, compact, handicap, EV, VIP, motorcycle), real-time occupancy, public display board
- **4-tier RBAC** -- User, premium, admin, superadmin with Laravel Sanctum token auth (7-day expiry)
- **Vehicle management** -- Photo upload, German licence plate city-code lookup (400+ codes)
- **Absence tracking** -- Homeoffice, vacation, sick leave with iCal import and team overview
- **Admin dashboard** -- Live occupancy, booking heatmaps, CSV export, custom branding, announcements, outbound webhooks
- **10 languages** -- EN, DE, FR, ES, IT, PT, TR, PL, JA, ZH with runtime hot-loading
- **PWA** -- Installable as native app, service worker for offline capability, Command Palette (Ctrl+K)
- **GDPR & German legal compliance** -- Art. 20 data export, Art. 17 erasure, DDG SS5, 7 legal templates, audited for DSGVO/TTDSG/UK GDPR/CCPA/nDSG
- **Observability** -- Prometheus metrics at `/api/metrics`, health endpoints, structured logging

### Security

- **httpOnly cookie auth** with SameSite=Lax (XSS-proof, Bearer fallback for APIs)
- bcrypt password hashing (12 rounds)
- Laravel Sanctum Bearer token authentication with real expiry enforcement
- Configurable password policies (length, uppercase, numbers, special chars)
- Per-endpoint rate limiting (login, register, payments)
- Nonce-based CSP (no unsafe-inline)
- Session management (list/revoke active tokens)
- Login history tracking with IP/user-agent
- API key authentication support
- SMTP password encryption in settings
- Full audit log with IP tracking
- Input validation on every endpoint
- Vehicle photo content validation via GD (prevents polyglot attacks)
- Admin middleware layer for `/api/v1/admin/*` routes

---

## Screenshots

| | |
|---|---|
| ![Dashboard](screenshots/05-dashboard.png) | ![Booking](screenshots/06-book.png) |
| Dashboard with occupancy stats | Interactive booking flow |
| ![Admin Panel](screenshots/09-admin.png) | ![Dark Mode](screenshots/10-dark-mode.png) |
| Admin panel with lot management | Full dark mode support |

---

## Architecture

```
                    ┌─────────────────────────────────┐
                    │     React 19 + Astro 6 SPA      │
                    │   TypeScript · Tailwind CSS 4    │
                    └───────────────┬─────────────────┘
                                    │ httpOnly Cookie + Bearer (Sanctum)
                    ┌───────────────▼─────────────────┐
                    │     Laravel 12 + PHP 8.4         │
                    │   /api/v1/*  · /api/metrics      │
                    │   /health/*  · Web Push (VAPID)  │
                    ├─────────────────────────────────┤
                    │  MySQL 8 · SQLite · PostgreSQL   │
                    └─────────────────────────────────┘
                        Docker · Shared Hosting · VPS
```

ParkHub PHP is designed for maximum deployment flexibility. It runs on 3 EUR/month shared hosting (Strato, IONOS, All-Inkl) with just PHP and MySQL, scales up to Docker Compose and Kubernetes, and supports PostgreSQL for cloud-native PaaS platforms like Render and Railway.

The same React 19 + Astro 6 frontend is shared with the [Rust edition](https://github.com/nash87/parkhub-rust), making both backends fully interchangeable.

---

## Module System

ParkHub PHP organizes functionality into 23 runtime-toggleable modules. All modules are enabled by default. Disable any module via `MODULE_*=false` environment variables.

| Module | Controller | Description |
|--------|-----------|-------------|
| Auth | `AuthController` | Login, register, 2FA, password reset, token refresh |
| Bookings | `BookingController` | Full booking lifecycle with conflict detection |
| Vehicles | `VehicleController` | Vehicle CRUD with photo upload and plate lookup |
| Absences | `AbsenceController` | Leave tracking with iCal import |
| Zones | `ZoneController` | Per-lot zone management |
| Slots | `ParkingSlotController` | Slot CRUD with status tracking |
| Lots | `ParkingLotController` | Lot management with layout editor |
| Recurring | `RecurringBookingController` | Recurring booking patterns |
| Guest | `GuestBookingController` | Guest bookings without accounts |
| Swap | `SwapRequestController` | Booking swap requests |
| Waitlist | `WaitlistController` | Waitlist for full lots |
| Favourites | `FavoriteController` | Favourite slot pinning |
| Recommendations | `RecommendationController` | Smart slot recommendations |
| Translations | `TranslationController` | Community translation management |
| Notifications | `NotificationController` | In-app notifications |
| Announcements | `AnnouncementController` | Admin announcements |
| Webhooks | `WebhookController` | Outbound webhooks with HMAC |
| Team | `TeamController` | Team overview and daily status |
| Admin | `AdminController` | Dashboard, reports, user management |
| Setup | `SetupController` | Installation wizard |
| GDPR | `UserController` | Data export and erasure |
| Demo | `DemoController` | Demo mode with auto-reset |
| Themes | `ThemeController` | 6 switchable design themes |

---

## Deployment Options

- **Shared Hosting** -- Upload via FTP, run `install.php`. Works with cPanel, Plesk, any PHP 8.2+ host. No SSH required.
- **Docker** -- Single container with PHP 8.4 + Apache, or Docker Compose with MySQL 8. Supports MySQL, SQLite, and PostgreSQL.
- **VPS / LAMP** -- Standard Laravel deployment on Ubuntu 24.04. Nginx or Apache, Composer, npm.
- **PaaS** -- One-click deploy on Railway, Render, and Fly.io. PostgreSQL support included.
- **Kubernetes** -- Health probes and Prometheus metrics ready.

See [docs/INSTALLATION.md](docs/INSTALLATION.md) for step-by-step guides.

---

## Testing

**1,300+ tests** -- 868 PHPUnit (backend) + 433 Vitest (frontend) + Playwright E2E. CI runs on every push via GitHub Actions. Lighthouse CI enforces accessibility >= 95, performance >= 90.

```bash
composer test                        # PHPUnit (868 tests)
cd parkhub-web && npx vitest run     # Frontend (433 tests)
npx playwright test                  # E2E
```

---

## Configuration

Key environment variables (full list in [docs/CONFIGURATION.md](docs/CONFIGURATION.md)):

| Variable | Purpose |
|----------|---------|
| `DB_CONNECTION` | `mysql`, `sqlite`, or `pgsql` |
| `DB_HOST` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Database connection |
| `DATABASE_URL` | Alternative single-URL format (PaaS platforms) |
| `MAIL_HOST` / `MAIL_USERNAME` / `MAIL_PASSWORD` | SMTP email |
| `PARKHUB_ADMIN_EMAIL` / `PARKHUB_ADMIN_PASSWORD` | Initial admin account |
| `DEMO_MODE=true` | Enable demo overlay with 6-hour auto-reset |

---

## API Documentation

The REST API mirrors the [Rust edition](https://github.com/nash87/parkhub-rust) endpoint structure at `/api/v1/*`. Full endpoint documentation is available in [docs/API.md](docs/API.md).

---

## Rust Edition

A feature-equivalent **Rust edition** (Axum + redb embedded DB) exists for environments that need a single binary with zero dependencies, AES-256-GCM database encryption, or a desktop client with system tray integration.

**[nash87/parkhub-rust](https://github.com/nash87/parkhub-rust)**

---

## Contributing

Contributions welcome -- see [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md) for setup and PR process.

Bug reports and feature requests: [GitHub Issues](https://github.com/nash87/parkhub-php/issues)

---

## License

MIT -- see [LICENSE](LICENSE).
