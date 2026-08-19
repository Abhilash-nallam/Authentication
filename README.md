# OTP Auth

Portable PHP + MySQL email OTP platform using Amazon SES.

## Current architecture

`Customer → optauth.com → PHP application → MySQL → Amazon SES`

The application does not require Cloudflare, Node.js, Redis, Docker or SSH. Cloudflare can be added later without changing core business logic.

## Implemented

- PHP 8.2+ / PDO / MySQL
- Amazon SES email delivery
- Secure OTP generation and keyed hashing
- OTP expiry, attempt limits and resend cooldowns
- Per-IP, per-email, per-project and verification rate limits
- Hashed/revocable API keys with project ownership
- Stable API error contract
- Optional request-ID OTP challenge binding
- Customer registration and email verification
- Secure customer login sessions and CSRF protection for cookie sessions
- Project creation and customer isolation
- Server-side DNS TXT domain verification
- OTP-Auth subdomain reservation with reserved-name and duplicate protection
- SES delivery event recording and secure event-ingestion endpoint
- Expired OTP/rate-limit/session cleanup command
- Customer dashboard

## Install

```bash
composer install
cp .env.example .env
mysql -u root -p otp_auth < database/schema.sql
```

For an existing Phase 1 database, apply `database/migrations/001_customer_platform.sql` once. That migration is not idempotent and should be executed only against the original Phase 1 schema.

Run locally:

```bash
php -S localhost:8080 -t public
```

Dashboard: `http://localhost:8080/`

## OTP API

- `POST /api/v1/otp/request`
- `POST /api/v1/otp/verify`
- `POST /api/v1/otp/resend`
- `GET /health`

See `docs/api.md`.

## Customer API

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
- `POST /api/v1/customer/key-revoke`

See `docs/customer-api.md`.

## Domain verification

A customer enters a real domain such as `slotcare.com`. OTP Auth generates a unique TXT token. The customer adds:

`TXT @ otp-auth-verification=<unique-token>`

The backend performs the DNS lookup and only then marks the project verified.

## Subdomains

After verification, a customer may reserve a slug such as `slotcare`, producing the logical hostname `slotcare.otp-auth.com`.

Actual DNS/hosting provisioning is deliberately provider-independent. InfinityFree or a later production DNS/hosting provider must be configured to route the reserved hostname to the application. The PHP application does not pretend that database insertion alone creates public DNS.

## Cleanup

Run periodically from a trusted scheduler:

```bash
php bin/cleanup.php
```

## Production gate

Do not purchase the public domain or enable uncontrolled customer traffic until the production test passes. The remaining external launch work includes HTTPS, SES production access, authenticated sending-domain DNS, bounce/complaint configuration, backups, monitoring and real end-to-end testing.
