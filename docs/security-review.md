# OTP Auth Security Review

This review records the security corrections applied during the customer-platform hardening pass.

## Corrected

- Signup control now treats malformed `signup_open` values safely instead of accidentally closing signup.
- Registration no longer exposes `Account already exists` through the public API.
- Signup is rate-limited by source IP and normalized email.
- Customer login is rate-limited independently by source IP and email and uses generic credential errors.
- Admin login has independent IP and email limits plus account lockout.
- Password-reset requests are rate-limited by IP and email and keep a generic response.
- Customer and admin session cookies use `HttpOnly`, `Secure` in production and `SameSite` attributes.
- Admin state-changing cookie requests require a CSRF token.
- API keys are checked for active/revoked/expired state, customer/project state and production environment.
- Configured API-key IP, origin, endpoint and purpose restrictions are enforced.
- OTP verification only accepts challenges in the `sent` state.
- OTP verification is serialized with a database row lock and an atomic consume transition to prevent concurrent double verification.
- OTP challenges that fail SES delivery are marked consumed/unusable.
- Request bodies are capped at 1 MiB.
- Forwarded client IP headers are trusted only from explicitly configured proxies.
- Baseline API security headers are emitted.
- Admin-editable global settings are allowlisted and type/range validated.
- Explicit security defaults are seeded in `003_security_defaults.sql`.
- Security regression checks are included in CI.

## Still required before production

- Execute the application against real PHP/MySQL/SES infrastructure.
- Verify HTTPS and HSTS deployment behavior.
- Configure trusted proxy addresses only when a real reverse proxy is present.
- Perform cross-tenant authorization tests with two customer accounts.
- Test DNS verification against real customer domains.
- Test SES bounce/complaint handling and event authenticity.
- Test backup/restore and cleanup jobs.
- Run external vulnerability scanning and penetration testing before accepting uncontrolled public traffic.
