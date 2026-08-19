# OTP Auth — Production SaaS Architecture

## Product identity

The planned public brand/domain is **OTP-AUTH.COM**. It is intentionally not purchased yet. Development uses a configurable `PLATFORM_DOMAIN` so the application can run on the current public hosting domain without hardcoding the future domain.

## Target production shape

```text
otp-auth.com
├── public website / registration / login
├── app.otp-auth.com       Customer Dashboard A
├── admin.otp-auth.com     Admin Dashboard B
├── api.otp-auth.com       OTP API
└── mail.otp-auth.com      SES custom MAIL FROM
```

## Runtime flow

```text
Customer Website / App
        |
        | HTTPS + project API key
        v
OTP Auth API (PHP)
        |
        +--> project/customer status + key authorization
        +--> layered abuse/rate limits
        +--> OTP generation / keyed hashing / expiry
        +--> MySQL OTP challenges + lifecycle events
        +--> Amazon SES
        |
        v
Recipient email
```

## Control plane

```text
Admin Dashboard B
        |
        +--> customers / suspension
        +--> projects/apps
        +--> API keys / revocation
        +--> OTP events / delivery visibility
        +--> security / permissions
        +--> non-secret global settings (future)
        |
        v
Customer Dashboard A
        |
        +--> account
        +--> projects/apps
        +--> domain TXT verification
        +--> OTP-Auth subdomain reservation
        +--> API keys / usage / documentation
        |
        v
OTP API
```

Admin changes must be enforced server-side. For example, suspending a customer disables the customer's active API keys and the OTP API rejects them.

## Domain and subdomain lifecycle

1. Customer creates a project/app.
2. Customer enters a domain such as `slotcare.com`.
3. OTP Auth generates a unique TXT verification token.
4. Customer publishes the TXT record.
5. OTP Auth performs the DNS lookup itself.
6. Only a successful lookup marks the project verified.
7. Customer chooses a unique subdomain slug such as `slotcare`.
8. The application reserves `slotcare.<PLATFORM_DOMAIN>`.
9. The actual DNS/hosting layer must route that hostname before public use.

After the final domain is purchased, set `PLATFORM_DOMAIN=otp-auth.com`, producing `slotcare.otp-auth.com`.

## Security boundaries

- OTP plaintext is never stored or returned.
- API keys are hashed and plaintext is shown only at creation/rotation.
- Customer recipient data is hashed/masked in operational logs.
- Customer and admin permissions are separate.
- Customer suspension is enforced at the API boundary.
- Rate limits are enforced at the API boundary.
- AWS secrets never belong in browser code.
- DNS verification is server-side.
- Cloudflare is optional and not a core application dependency.

## Hosting principle

The core backend remains portable PHP + MySQL. The production host can later be upgraded from the initial public/demo hosting environment to a proper PHP-capable production host with managed MySQL. Cloudflare can be introduced for DNS/CDN/WAF without changing the application contract.

## Launch gate

Do not purchase the final domain or expose uncontrolled public traffic until the real PHP/MySQL/SES production checklist passes.
