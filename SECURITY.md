# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 2.2.x   | Yes                |
| 2.1.x   | Yes                |
| 2.0.x   | Security fixes only |
| < 2.0   | No                 |

## Reporting a Vulnerability

**Do not open a public GitHub issue for security vulnerabilities.**

Instead, use one of these channels:

1. **GitHub Security Advisory** (preferred):
   [Create a private advisory](https://github.com/nash87/parkhub-php/security/advisories/new)

2. **Email**: Open a private security advisory on GitHub (see above)

Please include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact assessment
- Suggested fix (if available)

### Response Times

| Severity | Acknowledgement | Fix Timeline |
|----------|----------------|--------------|
| Critical | Within 24 hours | Within 7 days |
| High     | Within 48 hours | Within 14 days |
| Medium   | Within 72 hours | Within 30 days |
| Low      | Within 1 week   | Next release |

Researchers are credited in release notes unless anonymity is requested.

## Security Features

### Authentication
- **2FA/TOTP** -- QR code enrollment, backup codes, per-account toggle
- **bcrypt** password hashing (12 rounds, configurable via `BCRYPT_ROUNDS`)
- **Laravel Sanctum** opaque Bearer tokens with 7-day expiry
- **Token rotation** on password change (all existing tokens revoked)
- **3-tier RBAC** (user, admin, superadmin) enforced at controller level

### Transport Security
- Security headers via `.htaccess` (X-Content-Type-Options, X-Frame-Options, CSP, HSTS, Referrer-Policy)
- Server and framework version headers suppressed
- CORS restricted to specific allowed origins (no wildcard)

### Rate Limiting
- Login: 10 requests/minute per IP
- Registration: 10 requests/minute per IP
- Forgot password: 5 requests/15 minutes per IP
- Failed login attempts logged in audit log

### Input Validation
- All endpoints use `$request->validate()` with explicit rules
- Eloquent ORM and Query Builder with bound parameters (no raw SQL interpolation)
- Vehicle photo content validation via GD (`imagecreatefromstring()`) prevents polyglot attacks
- File uploads stored out of web root, served via controller endpoint

### Audit Log
- All write operations logged to `audit_log` table
- Login success/failure, registration, account deletion, GDPR erasure, password change
- Stores user ID, username, action, details (JSON), IP address, timestamp
- No delete endpoint -- deletion requires direct database access

### CSRF
- Pure SPA with Bearer token authentication -- CSRF via cookies is not applicable
- All state-changing requests require valid Bearer token in Authorization header

## Full Security Documentation

See [docs/SECURITY.md](docs/SECURITY.md) for the complete security model, architecture details, and audit log reference.

## CVE History

No CVEs have been reported against ParkHub PHP.
