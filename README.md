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
- project sender identities such as `verification@slotcare.otp-auth.com`

Cloudflare is optional. The core application remains portable PHP + MySQL.

## Customer architecture

`Customer → Dashboard A → Project → verified real domain → unique project.otp-auth.com → sender identities → Easy Integration / REST API → OTP service → MySQL → Amazon SES`

The platform deliberately follows the control-plane patterns used by developer email providers—projects, domains, API keys, sender identities, logs and usage—while implementing OTP-AUTH's own project/subdomain model. Brevo exposes sender/domain management and secret API keys; MailsSetu exposes domains, API keys, templates and logs. OTP-AUTH uses the same broad separation of customer control plane and delivery plane, but its core product is OTP authentication. 

## Implemented foundation

- PHP 8.2+ / PDO / MySQL
- Amazon SES email delivery
- Secure OTP generation and keyed hashing
- OTP expiry, attempt limits and resend cooldowns
- Layered per-IP, per-email, per-project and verification abuse controls
- Hashed/revocable secret project API keys
- Key rotation and lifecycle status
- Customer registration/email verification/login/logout/password reset
- Customer project isolation
- Server-side DNS TXT domain verification
- Unique OTP-AUTH project subdomain reservation with reserved-name protection
- Customer sender identity allocation such as `verification@slotcare.otp-auth.com`
- Widget allowed-origin controls
- Secure public-project browser widget without exposing secret API keys
- CORS preflight and origin enforcement for the widget API
- OTP lifecycle event storage and SES delivery event recording
- Admin authentication/control foundation
- Customer Dashboard A project workspace
- PHP CI syntax/smoke checks

## Install

```bash
composer install
cp .env.example .env
mysql -u root -p otp_auth < database/schema.sql
mysql -u root -p otp_auth < database/migrations/001_customer_platform.sql
mysql -u root -p otp_auth < database/migrations/002_saas_control_plane.sql
mysql -u root -p otp_auth < database/migrations/003_customer_senders_widget.sql
```

For an existing database, run the migrations once in order. They are upgrade scripts, not general-purpose rollback migrations.

Run locally:

```bash
php -S localhost:8080 -t public
```

Customer dashboard: `/`
Admin dashboard: `/admin`

## Easy Integration

The customer receives a public project ID, never a secret API key:

```html
<script src="https://YOUR-OTP-AUTH-HOST/widget.js"></script>
<div id="otp-auth"></div>
<script>
OtpAuth.init({ project: "PUBLIC_PROJECT_ID", purpose: "login" });
</script>
```

The browser widget sends OTP requests only when the request `Origin` matches the project's allowed origin. Server-side project status and rate/abuse controls are checked before an OTP is created.

## Developer API

- `POST /api/v1/otp/request`
- `POST /api/v1/otp/verify`
- `POST /api/v1/otp/resend`
- `GET /health`

Customer control-plane endpoints include registration, project creation, DNS verification, subdomain reservation, sender allocation, widget origin settings and API-key lifecycle operations. See `docs/api.md` and the customer API documentation.

## Domain and subdomain model

A customer enters a real domain such as `slotcare.com`. OTP Auth generates a unique TXT token. The customer adds it through any DNS provider. The backend performs the DNS lookup and only then marks the project verified.

A verified project can reserve a unique slug such as `slotcare`. The resulting logical hostname is `slotcare.PLATFORM_DOMAIN`; after `optauth.com` is purchased and deployed this becomes `slotcare.optauth.com`.

The customer can then allocate sender addresses under that project identity, for example `verification@slotcare.optauth.com`, `support@slotcare.optauth.com`, or another permitted local part. Address allocation is separate from SES/DNS verification: the platform reserves the identity first, while actual production sending requires the sender identity to be verified in the SES/DNS layer.

The PHP application never pretends that DNS/hosting provisioning happened. The selected DNS/hosting layer must actually route the wildcard/platform subdomains before public use.

## Production gate

The application is not considered production-ready merely because code exists in GitHub. Run the real PHP/MySQL/SES production test suite before launch. Domain purchase and public launch remain after successful testing.
