# OTP Auth

Portable PHP + MySQL email OTP platform using Amazon SES.

## Product direction

The long-term public platform is **OTP-AUTH.COM**. The domain is intentionally not purchased yet. Until launch, `PLATFORM_DOMAIN` points to the current public hosting domain so development does not depend on the final brand domain.

Target production shape:

- `otp-auth.com` — public website / registration / login
- `app.otp-auth.com` — Customer Dashboard A
- `admin.otp-auth.com` — Admin Dashboard B
- `api.otp-auth.com` — OTP API
- `mail.otp-auth.com` — SES custom MAIL FROM
- `otp@otp-auth.com` / `no-reply@otp-auth.com` — sender identities
- `support@otp-auth.com` — support

Cloudflare is optional. The core application remains portable PHP + MySQL.

## Current architecture

`Customer → Customer Dashboard → Project/App → API Key → PHP OTP API → MySQL → Amazon SES → recipient`

Admin B sits above the platform and controls customers, projects/apps, keys, OTP events, security and non-secret global settings.

## Implemented foundation

- PHP 8.2+ / PDO / MySQL
- Amazon SES email delivery
- Secure OTP generation and keyed hashing
- OTP expiry, attempt limits and resend cooldowns
- Layered per-IP, per-email, per-project and verification abuse controls
- Hashed/revocable project API keys
- Key rotation and lifecycle status
- Stable API error contract and request-ID binding
- Customer registration and email verification
- Secure customer login sessions and CSRF protection
- Customer project/app isolation
- Server-side DNS TXT domain verification
- Configurable platform subdomain reservation
- OTP lifecycle event storage
- SES delivery event recording
- Cleanup command for runtime data
- Admin authentication with role/permission foundation
- Admin customer/key/OTP-event control API
- Initial Admin Dashboard B UI
- Customer Dashboard A foundation
- PHP CI syntax/smoke checks

## Install

```bash
composer install
cp .env.example .env
mysql -u root -p otp_auth < database/schema.sql
mysql -u root -p otp_auth < database/migrations/001_customer_platform.sql
mysql -u root -p otp_auth < database/migrations/002_saas_control_plane.sql
```

For an existing Phase 1 database, run the migrations once in order. They are upgrade scripts, not general-purpose rollback migrations.

Create the first super admin after the migrations:

```bash
php bin/create_admin.php admin@example.com 'use-a-strong-password-here'
```

Run locally:

```bash
php -S localhost:8080 -t public
```

Customer dashboard: `/`
Admin dashboard: `/admin`

## OTP API

- `POST /api/v1/otp/request`
- `POST /api/v1/otp/verify`
- `POST /api/v1/otp/resend`
- `GET /health`

See `docs/api.md`.

## Customer platform API

- `POST /api/v1/customer/register`
- `POST /api/v1/customer/verify-email`
- `POST /api/v1/customer/login`
- `POST /api/v1/customer/logout`
- `POST /api/v1/customer/projects`
- `POST /api/v1/customer/project-create`
- `POST /api/v1/customer/domain-start`
- `POST /api/v1/customer/domain-verify`
- `POST /api/v1/customer/subdomain`
- `POST /api/v1/customer/key-create`
- `POST /api/v1/customer/keys`
- `POST /api/v1/customer/key-rotate`
- `POST /api/v1/customer/key-revoke`

## Admin platform API

- `POST /api/v1/admin/login`
- `POST /api/v1/admin/logout`
- `POST /api/v1/admin/me`
- `POST /api/v1/admin/overview`
- `POST /api/v1/admin/customers`
- `POST /api/v1/admin/customer-suspend`
- `POST /api/v1/admin/customer-reactivate`
- `POST /api/v1/admin/api-keys`
- `POST /api/v1/admin/key-revoke`
- `POST /api/v1/admin/otp-logs`

## Domain verification and subdomains

A customer enters a real domain such as `slotcare.com`. OTP Auth generates a unique TXT token. The customer adds it through their DNS provider. The backend performs the DNS lookup and only then marks the project verified.

A verified customer can reserve a slug such as `slotcare`. The resulting hostname is built from `PLATFORM_DOMAIN`. During development this can be the current public hosting domain. After `otp-auth.com` is purchased and deployed, set `PLATFORM_DOMAIN=otp-auth.com` and the same project logic produces `slotcare.otp-auth.com`.

The PHP application never pretends that DNS/hosting provisioning happened. The selected deployment/DNS layer must actually route the hostname before public use.

## Production gate

The application is not considered production-ready merely because code exists in GitHub. Run `docs/production-test.md` against the real PHP/MySQL/SES environment. Domain purchase and public launch remain after successful production testing.
