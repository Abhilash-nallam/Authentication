# OTP Auth Production SaaS Implementation Plan

This repository follows the uploaded production-development direction: **Admin Dashboard B first, Customer Dashboard A second, database/control-plane upgrades, SES/DNS production work, production hosting, then first-party website integration.** fileciteturn60file0

## Product identity

The final public platform is **OTP-AUTH.COM**. The domain will be purchased later, after the production test gate. Until then, `PLATFORM_DOMAIN` is configurable so the same code can operate on the current public hosting domain.

## Current implementation status

### Completed in code

- Phase 1 OTP API foundation.
- Stable API response/error contract.
- Request-ID challenge binding.
- Layered per-key and global IP/email/project/verification abuse controls.
- Project-scoped hashed API keys.
- Key rotation/revocation lifecycle.
- Customer registration and email verification.
- Secure customer sessions and CSRF protection.
- Customer project/app isolation.
- Server-side DNS TXT domain verification.
- Configurable OTP-Auth subdomain reservation.
- OTP lifecycle events and delivery state.
- SES send/failure event recording.
- Cleanup command and retention controls.
- Admin authentication, failed-login lockout and session management.
- Database-driven admin roles/permissions foundation.
- Admin customer suspension/reactivation.
- Admin API-key revocation.
- Admin OTP-event inspection.
- Initial Admin Dashboard B.
- Initial Customer Dashboard A.
- CI/smoke-check workflow.

## Architecture matched to the supplied plan

```text
OTP-AUTH.COM (future public domain)
        |
        +--> Public website / registration
        +--> Customer Dashboard A
        +--> Admin Dashboard B
        +--> OTP API
        |
        v
PHP application
        |
        +--> MySQL control plane + OTP data
        +--> Amazon SES
        +--> DNS TXT verification
```

Cloudflare remains optional at the application layer. The first deployment can continue with the current public domain/hosting environment; a proper production PHP host and managed MySQL should be used before uncontrolled public traffic.

## Admin Dashboard B — first control plane

Implemented foundation:

- Admin login.
- Eight-hour admin sessions.
- Five-failure temporary lockout.
- Super Admin / Support / Security / Read Only role foundation.
- Permission table foundation.
- Customer list.
- Customer suspension/reactivation.
- API-key inspection and revocation.
- OTP event inspection.
- Overview metrics.

Next Admin B expansion:

- Full customer detail page.
- App/project management.
- SES status page.
- DNS checklist.
- Security center and blocked entities UI.
- Global settings UI with database-backed non-secret settings.
- Admin-user management.
- Audit-log export.

## Customer Dashboard A

Implemented foundation:

- Registration/login.
- Email verification.
- Project creation/listing.
- API-key management API.
- Domain verification API.
- Subdomain reservation API.

Next Customer A expansion:

- Full app/project UI.
- OTP logs and analytics.
- Key environment/limits UI.
- Email branding.
- Webhooks.
- Documentation page.
- Usage/plan placeholder.
- Password reset and account security improvements.

## Database/control plane

Migration `002_saas_control_plane.sql` adds the control-plane structures from the supplied plan, including:

- `admin_users`
- `admin_roles`
- `admin_permissions`
- `admin_role_permissions`
- `admin_sessions`
- expanded `api_keys`
- expanded `otp_challenges`
- `otp_events`
- `customer_settings`
- `app_settings`
- `global_settings`
- `usage_daily`
- `webhooks`
- `webhook_deliveries`
- `blocked_entities`
- `ses_events`
- `billing_plans`
- `customer_plan_subscriptions`
- `abuse_limits`

The existing `projects` table is the current canonical backend representation of a customer app/project; the UI should call these **Apps/Projects** to match the product terminology without creating duplicate ownership models.

## SES / deliverability

Application support exists for SES sending, configuration sets, and send/failure event storage.

Still external:

- Final sending-domain purchase.
- SES domain identity verification.
- Easy DKIM.
- SPF.
- DMARC.
- Custom MAIL FROM.
- SES production access.
- Bounce/complaint event wiring.
- Real inbox/deliverability testing.

The supplied plan specifically highlights deliverability because the Phase 1 test email reached spam. fileciteturn60file0

## Domain/subdomain rule

Do not hardcode `otp-auth.com` into development logic.

During development:

`PLATFORM_DOMAIN=<current public hosting domain>`

After launch domain purchase:

`PLATFORM_DOMAIN=otp-auth.com`

Then a verified customer slug `slotcare` resolves logically to:

`slotcare.otp-auth.com`

The PHP application only reserves the name. Actual DNS/hosting routing must be configured at the infrastructure layer.

## Production gate

The code is **not** marked production-ready until the real environment passes:

1. Composer/PHP checks.
2. MySQL migration.
3. Customer registration + verification email.
4. Admin login/control.
5. Project creation.
6. Real DNS TXT verification.
7. Subdomain reservation and actual routing.
8. API key creation/rotation/revocation.
9. OTP send/resend/verify.
10. Wrong/expired/reused OTP tests.
11. Abuse-limit tests.
12. SES delivery/failure/bounce/complaint tests.
13. Backup/restore.
14. HTTPS/security validation.
15. Full production-test checklist.

Only after those pass should `otp-auth.com` be purchased and public production traffic opened, consistent with the supplied plan. fileciteturn60file0
