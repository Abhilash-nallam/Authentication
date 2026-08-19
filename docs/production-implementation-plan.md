# OTP Auth Production Implementation Goal and Plan

## Current implementation status

Phase A repository cleanup is complete. Phase B API contract hardening is now implemented in the repository. Phase C security hardening has begun with production configuration validation and verification throttling.

### Completed in the current implementation step

- Stable machine-readable API error codes and human-readable messages.
- Stable success response envelope.
- `request_id` validation and optional challenge binding during OTP verification.
- Independent verification request rate limiting.
- Production configuration checks for `APP_DEBUG`, `APP_KEY`, and `SES_FROM_EMAIL`.
- Updated API documentation to match the hardened contract.
- Expanded smoke-test coverage for configuration and validation paths.

### Intentionally not implemented yet

- Customer registration/project management.
- Customer-owned domain DNS verification.
- OTP-Auth customer subdomain provisioning.
- Browser JavaScript widget.
- Full customer dashboard/admin system.
- SES bounce/complaint event processing.
- Database migration framework and cleanup scheduler.

Those belong to later product stages and should not be mixed into the current Phase 1 API hardening work.

## 1. Product goal

OTP Auth is planned as a production-ready email OTP verification platform. The first production version should let a customer application request, resend, and verify one-time passwords through an authenticated HTTP API while OTP Auth handles OTP generation, secure storage, expiry, resend cooldowns, verification attempts, logging, and email delivery through Amazon SES.

The immediate goal is to harden the current Phase 1 implementation until it can safely support real registration, login, password-reset, and generic verification flows for a test customer application.

## 2. Current repository concept

The repository is a PHP 8.2+ project using Composer, PDO/MySQL, phpdotenv, and the AWS SDK for PHP. The application exposes `/api/v1/otp/request`, `/api/v1/otp/verify`, `/api/v1/otp/resend`, and `/health` from the public front controller.

At a high level, the current architecture is:

1. Customer website or app calls OTP Auth API over HTTPS with a bearer API key.
2. OTP Auth validates the API key.
3. OTP Auth validates request input and rate limits the caller.
4. OTP Auth creates or verifies an OTP challenge in MySQL.
5. OTP Auth sends OTP email through Amazon SES.
6. OTP Auth returns a stable JSON response contract to the customer application.
7. Logs and database records support audit and debugging without storing plaintext OTP values.

## 3. Production implementation phases

### Phase A — Repository cleanup and baseline verification

Status: **Completed.**

The repository has a canonical PHP public entrypoint, stale demo assets were removed, environment examples were updated, and the smoke test was expanded.

### Phase B — API contract hardening

Status: **Implemented.**

The V1 contract now defines stable success/error envelopes, machine-readable error codes, optional `request_id` challenge binding, verification throttling, and explicit retry behavior. See `docs/api.md`.

The current V1 request endpoint does not provide an `Idempotency-Key` contract. Clients should not blindly retry unknown-outcome requests because a new OTP invalidates the previous active challenge. A future API revision can introduce explicit idempotency semantics.

### Phase C — Security hardening

Status: **In progress.**

Implemented:

- Required production `APP_KEY` validation.
- Production debug-mode protection.
- Production SES sender validation.
- Verification request rate limiting.
- Request ID challenge binding.
- Stable security-related error codes.

Next:

- Per-email/IP/project abuse controls.
- API-key revocation/listing workflow.
- Log redaction review.
- Secure dashboard session configuration.
- Infrastructure-level protection planning.

### Phase D — Database and operations readiness

Status: Pending.

Next work:

- Managed migrations or documented migration process.
- Expired OTP/rate-limit cleanup.
- Query/index review.
- Backup and restore procedure.
- Environment-specific configuration.

### Phase E — Amazon SES production readiness

Status: Pending external AWS configuration.

Next work:

- Verify sender identity.
- Confirm SES sandbox/production status.
- Configure final sending domain after the production test gate.
- Add configuration-set delivery/bounce/complaint observability.

### Phase F — Dashboard/admin implementation

Status: Pending.

The current dashboard remains a Phase 1 test dashboard. A real authenticated customer/admin dashboard will be implemented after the core API/security foundation is stable.

### Phase G — Observability and incident response

Status: Pending.

Add correlation IDs, metrics, centralized logs, alerts, and incident procedures.

### Phase H — Production test and launch gate

Status: Pending.

Execute the complete production-test checklist only after the above foundations are ready. Domain purchase and public launch remain launch-gate activities after successful production testing.

## 4. Next coding backlog

1. Complete per-email/IP/project abuse controls.
2. Add API-key listing, rotation, and revocation workflow.
3. Add automated OTP lifecycle and rate-limit tests using a test database.
4. Add cleanup command for expired OTP/rate-limit records.
5. Harden dashboard authentication and session handling.
6. Add SES delivery observability skeleton.
7. Then begin customer registration/project/domain-verification architecture.

## 5. Product architecture after Phase 1

After the Phase 1 API is production-tested, the platform can grow into:

`optauth.com → customer account → project → verified customer domain → OTP-Auth subdomain → easy integration / REST API → OTP service → Amazon SES`

The customer-domain verification and subdomain system is deliberately not coupled to the current OTP core yet.

## 6. Hosting principle

The application remains portable PHP + MySQL and does not require Cloudflare, Node.js, Redis, Docker, SSH, or AWS-specific runtime infrastructure. Cloudflare can be placed in front later without changing the core business logic.
