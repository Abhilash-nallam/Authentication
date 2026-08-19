# OTP Auth Production Implementation Goal and Plan

## 1. Product goal

OTP Auth is planned as a production-ready email OTP verification platform. The first production version should let a customer application request, resend, and verify one-time passwords through an authenticated HTTP API while OTP Auth handles OTP generation, secure storage, expiry, resend cooldowns, verification attempts, logging, and email delivery through Amazon SES.

The immediate goal is not to build every future feature. The immediate goal is to harden the current Phase 1 implementation until it can safely support real registration, login, password-reset, and generic verification flows for a test customer application.

## 2. Current repository concept

The repository is a PHP 8.2+ project using Composer, PDO/MySQL, phpdotenv, and the AWS SDK for PHP. The application exposes `/api/v1/otp/request`, `/api/v1/otp/verify`, `/api/v1/otp/resend`, and `/health` from the public front controller.

At a high level, the current architecture is:

1. Customer website or app calls OTP Auth API over HTTPS with a bearer API key.
2. OTP Auth validates the API key.
3. OTP Auth validates request input and rate limits the caller.
4. OTP Auth creates or verifies an OTP challenge in MySQL.
5. OTP Auth sends OTP email through Amazon SES.
6. OTP Auth returns a clear JSON response to the customer application.
7. Logs and database records support audit and debugging without storing plaintext OTP values.

## 3. Current repository map

| Area | Files | Purpose |
| --- | --- | --- |
| Bootstrap/config | `bootstrap.php`, `src/Config.php` | Load Composer, environment variables, storage directory, UTC timezone, and typed config helpers. |
| Routing/API entry | `public/index.php`, `src/ApiController.php` | Route health checks and OTP endpoints, authenticate API keys, validate payloads, apply rate limits, and call OTP service methods. |
| Persistence | `src/Database.php`, `database/schema.sql` | Connect to MySQL and define tables for API keys, OTP challenges, rate limits, audit logs, and dashboard users. |
| API keys | `src/ApiKeyService.php`, `bin/create_api_key.php` | Generate one-time-visible API keys, store SHA-256 hashes, authenticate active keys, and update last-used timestamps. |
| OTP logic | `src/OtpService.php`, `src/Validation.php` | Generate numeric OTPs, hash OTPs with application secret material, expire challenges, enforce attempts, consume successful OTPs, and validate input. |
| Email | `src/EmailTemplate.php`, `src/OtpService.php` | Render a branded OTP email and deliver it with Amazon SES v2. |
| Rate limiting | `src/RateLimiter.php` | Enforce configurable request and resend limits per API key and bucket. |
| Responses/logs | `src/Response.php`, `src/Logger.php` | Return no-store JSON responses and write structured application logs. |
| Dashboard/docs/tests | `public/dashboard.php`, `public/assets/app.css`, `docs/*.md`, `tests/smoke.php` | Provide a basic dashboard, API/architecture/production-test documentation, and a minimal smoke check. |

## 4. Production implementation principles

Before coding new functionality, we should align on these rules:

- Keep Phase 1 focused on reliable email OTP verification.
- Never store plaintext OTP values, API keys, AWS secrets, or database passwords.
- Use HTTPS only in production.
- Keep AWS credentials out of JavaScript, HTML, logs, and committed files.
- Prefer managed secrets, IAM roles, centralized logs, backups, and monitoring in production.
- Use stable JSON contracts so customer applications can integrate safely.
- Treat domain purchase and public launch as a launch gate after production testing succeeds.

## 5. Production implementation phases

### Phase A — Repository cleanup and baseline verification

Deliverables:

- Confirm one canonical public app path and remove or clearly separate old/demo dashboard assets.
- Add or verify `.env.example` with all required variables and safe defaults.
- Confirm `composer install` and autoloading work cleanly.
- Add repeatable local setup instructions for MySQL schema import and API key creation.
- Run the existing smoke test and add more automated tests where possible.

Acceptance criteria:

- A new developer can install dependencies, configure `.env`, import the schema, create an API key, and run `/health` from README instructions.
- There is no confusion between demo endpoints and production endpoints.

### Phase B — API contract hardening

Deliverables:

- Finalize request/response schemas for request, verify, resend, and health endpoints.
- Add consistent machine-readable error codes in addition to human-readable messages.
- Decide whether `request_id` is required for verification or whether latest active challenge by email/purpose remains the contract.
- Add idempotency or duplicate-send behavior rules if customer apps may retry failed requests.
- Add API versioning rules for future changes.

Acceptance criteria:

- `docs/api.md` fully matches implementation behavior.
- Customer apps can reliably distinguish invalid input, invalid API key, rate limit, expired OTP, too many attempts, delivery failure, and server error.

### Phase C — Security hardening

Deliverables:

- Require `APP_KEY` in production instead of allowing a development fallback.
- Add secure production configuration checks that fail fast when required secrets or SES sender settings are missing.
- Review rate-limit buckets for abuse cases: per IP, per email, per API key, resend cooldown, and verification brute force.
- Ensure logs never contain OTP values, API keys, raw email addresses if not needed, or secret material.
- Add API key revocation/listing/admin workflow.
- Add CORS policy only if browser clients are intentionally supported.
- Plan WAF/reverse-proxy protections for public deployment.

Acceptance criteria:

- Production mode cannot start with unsafe placeholder secrets.
- OTP brute force, resend spam, and high-volume API abuse are rate limited at application and infrastructure layers.

### Phase D — Database and operations readiness

Deliverables:

- Convert the single schema file into managed migrations or a documented migration process.
- Add retention/cleanup process for expired OTP challenges and old rate-limit rows.
- Add database indexes based on final query patterns.
- Define backup, restore, and point-in-time recovery expectations.
- Add environment-specific configuration for local, staging, and production.

Acceptance criteria:

- Production data can be migrated forward safely.
- Expired/security-sensitive records have a retention policy.
- Database recovery is tested before launch.

### Phase E — Amazon SES production readiness

Deliverables:

- Verify sender identity in SES for initial tests.
- Request SES production access if the account is still sandboxed.
- Decide AWS region and sending domain.
- After production test succeeds, purchase/configure domain, then set DKIM/SPF/DMARC DNS records.
- Add SES configuration set for bounce, complaint, and delivery events.
- Add bounce/complaint handling plan before high-volume launch.

Acceptance criteria:

- Test recipients receive OTP emails reliably.
- Domain-authenticated sending is configured before public customer traffic.
- Bounce/complaint events are observable and actionable.

### Phase F — Dashboard/admin implementation

Deliverables:

- Decide dashboard scope for Phase 1: health, API keys, usage, logs, settings, and test send.
- Implement dashboard authentication with secure sessions and password hashing.
- Add role/permission model if multiple administrators are needed.
- Separate demo-only local browser data from production data.
- Add audit logs for administrative actions.

Acceptance criteria:

- Dashboard access is protected.
- Admin actions can be audited.
- Dashboard cannot expose secret API key values after creation.

### Phase G — Observability and incident response

Deliverables:

- Add structured request IDs/correlation IDs.
- Centralize logs and metrics.
- Track API latency, error rate, SES delivery failures, OTP request volume, verify success rate, rate-limit events, and database health.
- Add alerts for elevated failures, SES bounces/complaints, unusual request spikes, and database errors.
- Document incident steps for email delivery outage, compromised API key, database outage, and abuse spike.

Acceptance criteria:

- Operators can identify whether a problem is API, database, rate limit, SES, or customer integration related.
- Common incidents have documented response steps.

### Phase H — Production test and launch gate

Deliverables:

- Execute the production test checklist end to end.
- Test registration, login, password reset, and generic flows from a customer-like app.
- Test wrong OTP, expired OTP, too many attempts, resend cooldown, invalid API key, revoked API key, and SES failure behavior.
- Disable debug mode.
- Confirm HTTPS, secrets, backups, monitoring, and retention tasks.
- Only after the production test passes, proceed with domain purchase and public DNS/SES domain setup.

Acceptance criteria:

- Every launch-gate checklist item is passing or intentionally deferred with written risk acceptance.
- The system is ready for a small controlled production pilot.

## 6. Proposed first coding backlog

When coding begins, the recommended order is:

1. Add complete `.env.example` and production configuration validation.
2. Reconcile dashboard/demo files and remove endpoint confusion.
3. Add machine-readable API error codes and update API docs.
4. Add per-email and verify-attempt rate limits beyond the current API-key/IP and resend controls.
5. Add automated tests for validation, API key hashing/authentication, OTP lifecycle, and rate limiting.
6. Add cleanup command for expired OTP/rate-limit records.
7. Add dashboard authentication and API key management screens.
8. Add SES delivery observability plan or event webhook skeleton.

## 7. Open decisions before production coding

- Final hosting provider and deployment topology.
- Managed database provider and backup policy.
- AWS region and SES account setup.
- Whether customer apps will call OTP Auth only server-to-server or directly from browsers/mobile clients.
- Whether verification should require `request_id` for stronger challenge binding.
- Final dashboard user roles and admin features.
- Pricing, quotas, and customer onboarding process.
- Domain purchase timing after production test success.

## 8. Definition of done for Phase 1 production pilot

Phase 1 is ready for a controlled production pilot when:

- API endpoints are documented, versioned, tested, and stable.
- OTPs and API keys are never stored in plaintext.
- Rate limits protect request, resend, and verification flows.
- SES sends authenticated email from a verified production identity/domain.
- Dashboard/admin access is secured.
- Logs, metrics, alerts, backups, and cleanup tasks are operational.
- A production-like customer app completes registration, login, and password reset OTP flows.
- The production test checklist passes.
