# AGENTS.md — ParkHub PHP

## Overview
Laravel-based ParkHub deployment with PHP backend, React/Vite frontend assets, Playwright tests, container builds, and GitHub Actions automation. Treat this repository as production code with security, privacy, CI safety, and operational correctness as primary concerns.

## Stack
- PHP 8.4
- Laravel
- React + Vite frontend in `parkhub-web/`
- Additional frontend assets in `resources/js/`
- Composer, npm, Playwright, Docker

## Repo Map
- `app/`, `routes/`, `database/` backend code, routing, and schema changes
- `resources/js/` app frontend assets
- `parkhub-web/` standalone frontend bundle
- `tests/` PHP and browser test coverage
- `.github/` repository automation, PR review, and security scanning

## Build And Test
```sh
composer install
php artisan test
npm ci
npm run build
cd parkhub-web && npm ci && npm run build
```

## Audit Priorities
- Review authn/authz, session safety, CSRF, validation, file handling, and migration safety first.
- Treat legal/GDPR flows, audit logging, account recovery, and admin features as high-risk paths.
- Check CI/CD, GHCR publishing, and Render deployment workflows for token scope and unsafe triggers.
- Require regression tests for high-risk backend and frontend changes.

## Guardrails
- Never commit secrets, `.env`, demo credentials beyond explicit demo-only configuration, or production API keys.
- Do not relax validation, authorization, rate limits, or audit logging without explicit justification.
- Do not broaden workflow permissions when narrower permissions work.
- Prefer minimal safe fixes and document stronger long-term hardening separately.
