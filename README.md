<p align="center">
  <img src="public/favicon.svg" alt="ParkHub PHP" width="96">
</p>

<h1 align="center">ParkHub PHP — Self-Hosted Parking Management</h1>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-blue.svg?style=for-the-badge" alt="MIT License"></a>
  <a href="CHANGELOG.md"><img src="https://img.shields.io/badge/Release-v1.9.0-brightgreen.svg?style=for-the-badge" alt="v1.9.0"></a>
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.4-777BB4.svg?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.4"></a>
  <a href="https://laravel.com/"><img src="https://img.shields.io/badge/Laravel-12-FF2D20.svg?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12"></a>
  <a href="https://react.dev/"><img src="https://img.shields.io/badge/React-19-61DAFB.svg?style=for-the-badge&logo=react&logoColor=black" alt="React 19"></a>
  <a href="docs/GDPR.md"><img src="https://img.shields.io/badge/DSGVO-konform-green.svg?style=for-the-badge" alt="GDPR Compliant"></a>
  <a href="COMPLIANCE-REPORT.md"><img src="https://img.shields.io/badge/Compliance-Audited-brightgreen.svg?style=for-the-badge" alt="Compliance Audited"></a>
  <a href="docker-compose.yml"><img src="https://img.shields.io/badge/Docker-ready-2496ED.svg?style=for-the-badge&logo=docker&logoColor=white" alt="Docker Ready"></a>
  <a href="https://github.com/nash87/parkhub-php/actions/workflows/ci.yml"><img src="https://github.com/nash87/parkhub-php/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
</p>

<p align="center">
  <strong>Ihre Daten. Ihr Server. Ihre Kontrolle.</strong><br>
  The on-premise parking management platform that runs anywhere — shared hosting, VPS, Docker, or Kubernetes.<br>
  Built with Laravel 12 and React 19. Zero cloud. Zero tracking. 100% GDPR compliant by design.
</p>

<p align="center">
  <a href="https://parkhub-php-demo.onrender.com"><strong>Try the Live Demo</strong></a> &nbsp;·&nbsp;
  <a href="docs/INSTALLATION.md">Installation</a> &nbsp;·&nbsp;
  <a href="docs/API.md">API Docs</a> &nbsp;·&nbsp;
  <a href="docs/GDPR.md">GDPR Guide</a> &nbsp;·&nbsp;
  <a href="CHANGELOG.md">Changelog</a>
</p>

---

## Why Self-Hosted?

Most parking management SaaS costs €200–2,000/month, stores your data on US cloud infrastructure, and requires a data processing agreement just to get started.

ParkHub is different. It runs on your server — a shared hosting plan, a VPS, or your company network. Your data never leaves your premises, which means **no GDPR processor agreement needed**, no CLOUD Act exposure, and no monthly fees. The entire source code is MIT-licensed and auditable.

---

## Quick Start

```bash
git clone https://github.com/nash87/parkhub-php.git && cd parkhub-php
docker compose up -d
# Open http://localhost:8080 — Login: admin@parkhub.test / demo
```

The first build takes 2–5 minutes (installs Composer + Node dependencies, builds the React frontend). After that, starts are instant. Custom credentials from the start:

```bash
PARKHUB_ADMIN_EMAIL=you@company.com PARKHUB_ADMIN_PASSWORD=secure docker compose up -d
```

**No Docker?** ParkHub PHP runs on any €3/month shared hosting with PHP 8.2+ and MySQL. Upload via FTP, open `install.php` in your browser, done. See [Installation Guide →](docs/INSTALLATION.md)

**[Live Demo →](https://parkhub-php-demo.onrender.com)** &nbsp; Login: `admin@parkhub.test` / `demo` &nbsp; (auto-resets every 6 hours)

---

## What You Get

**Booking System** — Full booking lifecycle with automatic pricing (hourly rate × duration, 19% German VAT, daily max cap). One-tap quick booking, recurring reservations, guest bookings without accounts, swap requests, waitlists, and automatic no-show release. QR code check-in per booking and per slot. iCal export for calendar integration. Email confirmations via SMTP and Web Push notifications.

**Smart Recommendations** — A heuristic scoring engine learns from usage patterns and suggests the best available slots. Users can pin favorites for one-tap access.

**Parking Lot Management** — Create multiple lots with per-lot zones, slot types (standard, compact, handicap, EV, VIP, motorcycle), and slot features (EV charging, covered, near exit, security camera). Per-lot pricing with hourly rates, daily maximums, and monthly passes. Visual layout editor with real-time occupancy and a public display board for lobby screens.

**User & Access Control** — Four-tier RBAC (user, premium, admin, superadmin) with Laravel Sanctum token auth (7-day expiry). Vehicle management with photo upload and German licence plate city-code lookup (400+ codes). Bulk user import via CSV (up to 500 users). Absence tracking with iCal import, recurring homeoffice patterns, and team overview.

**Admin Dashboard** — Live occupancy stats, booking heatmaps by weekday and hour, CSV export, custom branding (logo, company name, primary color), announcements, and outbound webhooks for event-driven integrations. Prometheus metrics at `/api/metrics` and health endpoints for monitoring.

**Internationalization** — Ships with 10 languages (EN, DE, FR, ES, IT, PT, TR, PL, JA, ZH). Community translation proposals with voting and admin review. Runtime overrides hot-loaded without restarts.

**PWA** — Installable as a native app on phones and desktops. Service worker for offline capability. Command Palette (Ctrl+K) for quick navigation.

**GDPR & German Legal Compliance** — Art. 20 data export, Art. 17 erasure with both anonymization and hard CASCADE deletion, DDG §5 Impressum, configurable retention days, and 7 ready-to-use legal templates. Audited for DSGVO, TTDSG, UK GDPR, CCPA, and nDSG. [Full Compliance Report →](COMPLIANCE-REPORT.md)

**Security** — bcrypt password hashing (12 rounds), per-endpoint rate limiting, full audit log, input validation on every endpoint, vehicle photo content validation via GD (prevents polyglot attacks), and admin role checks independent of middleware. [Security Audit →](SECURITY-AUDIT.md)

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
                                    │ Bearer Token (Sanctum)
                    ┌───────────────▼─────────────────┐
                    │     Laravel 12 + PHP 8.4         │
                    │   /api/v1/*  · /api/metrics      │
                    │   /health/*  · Web Push (VAPID)  │
                    ├─────────────────────────────────┤
                    │  MySQL 8 · SQLite · PostgreSQL   │
                    └─────────────────────────────────┘
                        Docker · Shared Hosting · VPS
```

ParkHub PHP is designed for maximum deployment flexibility. It runs on €3/month shared hosting (Strato, IONOS, All-Inkl) with just PHP and MySQL, scales up to Docker Compose and Kubernetes, and supports PostgreSQL for cloud-native PaaS platforms like Render and Railway.

The same React 19 + Astro 6 frontend is shared with the [Rust edition](https://github.com/nash87/parkhub-rust), making both backends fully interchangeable.

---

## Deployment Options

**Shared Hosting** — Upload via FTP, run `install.php` in your browser. Works with cPanel, Plesk, and any PHP 8.2+ host. No SSH required.

**Docker** — Single container with PHP 8.4 + Apache, or Docker Compose with MySQL 8. Supports MySQL, SQLite, and PostgreSQL via environment variables.

**VPS / LAMP** — Standard Laravel deployment on Ubuntu 24.04. Nginx or Apache, Composer, npm.

**PaaS** — One-click deploy on Railway, Render, and Fly.io. PostgreSQL support included via the Docker image.

**Kubernetes** — Health probes and Prometheus metrics ready. Manifests coming soon.

See [docs/INSTALLATION.md](docs/INSTALLATION.md) for step-by-step guides for every platform.

---

## Testing

**484 PHPUnit tests** (backend) + **401 Vitest tests** (frontend) + Playwright E2E. CI runs on every push via GitHub Actions.

```bash
composer test               # PHPUnit
cd parkhub-web && npx vitest run  # Frontend
npx playwright test         # E2E
```

---

## Configuration

Key environment variables (full list in [docs/CONFIGURATION.md](docs/CONFIGURATION.md)):

- `DB_CONNECTION` — `mysql`, `sqlite`, or `pgsql`
- `DB_HOST` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` — Database connection
- `DATABASE_URL` — Alternative single-URL format (PaaS platforms)
- `MAIL_HOST` / `MAIL_USERNAME` / `MAIL_PASSWORD` — SMTP email
- `PARKHUB_ADMIN_EMAIL` / `PARKHUB_ADMIN_PASSWORD` — Initial admin account
- `DEMO_MODE=true` — Enable demo overlay with 6-hour auto-reset

---

## Rust Edition

A feature-equivalent **Rust edition** (Axum + redb embedded DB) exists for environments that need a single binary with zero dependencies, AES-256-GCM database encryption, or a desktop client with system tray integration.

**[nash87/parkhub-rust →](https://github.com/nash87/parkhub-rust)**

---

## License

MIT — see [LICENSE](LICENSE).

---

## Contributing

Contributions welcome — see [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md) for setup and PR process.

Bug reports and feature requests: [GitHub Issues](https://github.com/nash87/parkhub-php/issues)
