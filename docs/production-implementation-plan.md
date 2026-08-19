# OTP Auth Production Implementation Goal and Plan

## Current implementation status

The repository has progressed from the Phase 1 OTP service into the first customer-platform layer.

### Completed in the current implementation

- Stable machine-readable API error codes and success envelope.
- Optional `request_id` challenge binding.
- Per-IP, per-email, per-project and verification rate limits.
- Project isolation for new customer API keys.
- API-key listing and revocation; creating a new key provides rotation capability.
- Production configuration validation for `APP_KEY`, debug mode and SES sender.
- Customer registration with password hashing and email verification.
- Secure customer sessions using HTTP-only cookies and CSRF protection for browser sessions.
- Customer project creation and isolation.
- Server-side DNS TXT domain verification.
- Encrypted-at-rest domain verification token material plus hash comparison.
- Reserved-name and duplicate protection for OTP-Auth subdomain reservation.
- Expired OTP/rate-limit/session cleanup command.
- SES send/failure event recording and a protected event-ingestion endpoint.
- Authenticated customer dashboard foundation.
- PHP CI syntax and smoke checks.
- Customer API documentation.

### Still requires real environment validation

- MySQL migration execution against the existing database.
- PHP/Composer/SES end-to-end execution.
- SES sandbox/production configuration.
- Real DNS TXT verification against a customer domain.
- Actual DNS/hosting provisioning for `slotcare.otp-auth.com`.
- SES bounce/complaint provider configuration and signed event delivery.
- Full browser/widget integration.
- Backups, monitoring, WAF/infrastructure controls and production launch testing.

## 1. Product goal

OTP Auth is planned as a production-ready email OTP verification platform. The first production version should let a customer application request, resend, and verify one-time passwords through an authenticated HTTP API while OTP Auth handles OTP generation, secure storage, expiry, resend cooldowns, verification attempts, logging, and email delivery through Amazon SES.

The broader customer journey is:

`Customer registers → verifies email → creates project → enters website domain → DNS TXT verification → chooses OTP-Auth subdomain → creates API credentials → integrates OTP service.`

## 2. Production implementation phases

### Phase A — Repository cleanup and baseline verification

**Completed.**

### Phase B — API contract hardening

**Completed.**

Stable response/error contracts, request-ID binding, retry guidance and documentation are implemented in `docs/api.md`.

### Phase C — Security hardening

**Substantially implemented; runtime validation remains.**

Implemented:

- Production secret/debug checks.
- Per-IP/email/project/verification limits.
- API-key revocation/listing.
- Customer session security and CSRF controls.
- Project ownership checks.
- Domain verification server-side validation.
- Secret material excluded from API responses and logs where designed.

Remaining: infrastructure-level controls and a final log-redaction/security review during the production test.

### Phase D — Database and operations readiness

**Partially implemented.**

- Fresh-install schema includes customer/project/domain/session/event data.
- A one-time migration is provided for the existing Phase 1 database.
- Cleanup command exists for expired OTP, rate-limit and session data.

Remaining: execute the migration, review production indexes, test backup/restore and schedule cleanup.

### Phase E — Amazon SES production readiness

**Application support implemented; AWS setup remains external.**

- SES sending is implemented.
- SES configuration-set name is supported.
- Send/failure events are recorded.
- Protected event ingestion exists as an application boundary.

Remaining: SES production access, verified sending domain, DKIM/SPF/DMARC, configuration-set/SNS event wiring, bounce/complaint handling and real delivery testing.

### Phase F — Dashboard/admin implementation

**Customer dashboard foundation implemented.**

It now supports customer registration/login, project creation and project listing. Domain verification, subdomain reservation and API-key management are exposed through the customer API and can be added to the UI without changing the core services.

### Phase G — Observability and incident response

**Partially implemented.**

Structured application logging and email-event persistence exist. CI syntax/smoke checks are included.

Remaining: centralized metrics, alerts and formal incident runbooks.

### Phase H — Production test and launch gate

**Pending.**

This cannot be honestly marked complete until the application has been executed against the real PHP/MySQL/SES environment and the production checklist passes.

## 3. Customer-platform implementation

The customer platform now follows:

`Customer → account → project → domain TXT verification → verified project → reserved OTP-Auth subdomain → project API key → OTP API`

A reserved `slotcare` slug is represented as `slotcare.otp-auth.com` in the project record. Public DNS creation is intentionally not faked by the PHP application; it must be performed by the selected DNS/hosting deployment layer.

## 4. Next required real-world validation

1. Run Composer install and PHP CI checks.
2. Import a fresh schema or run the one-time migration against the existing database.
3. Generate a customer account and verify email through SES.
4. Create a project.
5. Verify a real domain with a DNS TXT record.
6. Reserve a subdomain.
7. Create a project API key.
8. Send, resend and verify real OTPs.
9. Test wrong/expired/reused OTPs and abuse limits.
10. Test API-key revocation.
11. Test SES delivery/failure events.
12. Execute the production-test checklist.

Only after these pass should public domain purchase/DNS launch and uncontrolled customer traffic proceed.

## 5. Hosting principle

The application remains portable PHP + MySQL and does not require Cloudflare, Node.js, Redis, Docker or SSH. Cloudflare is optional and can be introduced later without changing core business logic.
