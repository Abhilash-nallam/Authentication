# Customer Platform API

All endpoints use `POST /api/v1/customer/<action>` with JSON. Customer sessions use an HTTP-only `otp_auth_session` cookie; server integrations may instead send `Authorization: Bearer <session_token>`.

## Account

`POST /api/v1/customer/register`

```json
{"email":"owner@example.com","password":"at-least-10-characters"}
```

A verification email is sent through SES. The account remains pending until verified.

`POST /api/v1/customer/verify-email`

```json
{"token":"verification-token"}
```

`POST /api/v1/customer/login` returns a secure session cookie and CSRF token.

`POST /api/v1/customer/logout`

## Projects

`POST /api/v1/customer/projects` — list the authenticated customer's projects.

`POST /api/v1/customer/project-create`

```json
{"name":"SlotCare"}
```

## Domain verification

`POST /api/v1/customer/domain-start`

```json
{"project_id":1,"domain":"slotcare.com"}
```

The response provides a TXT record such as `otp-auth-verification=<unique-token>`. The customer adds it through their DNS provider.

`POST /api/v1/customer/domain-verify`

```json
{"project_id":1}
```

The backend performs the DNS TXT lookup; browser-supplied verification results are never trusted.

## OTP-Auth subdomain

After domain verification:

`POST /api/v1/customer/subdomain`

```json
{"project_id":1,"subdomain":"slotcare"}
```

The application reserves `slotcare.otp-auth.com` after validating the slug and ownership. Actual DNS/hosting provisioning remains an infrastructure deployment step because the PHP application must remain independent of Cloudflare or another specific DNS provider.

## API keys

`POST /api/v1/customer/key-create`

```json
{"project_id":1,"name":"Production server"}
```

The plaintext API key is returned only at creation time.

`POST /api/v1/customer/keys` lists metadata only.

`POST /api/v1/customer/key-rotate`

```json
{"project_id":1,"old_key_id":123,"name":"Production rotated key"}
```

Rotation revokes the old key and creates the new key in one database transaction. The new plaintext key is returned only once.

`POST /api/v1/customer/key-revoke`

```json
{"project_id":1,"key_id":123}
```

Revocation immediately prevents API authentication.

## Browser security

When a browser session cookie is used, state-changing customer requests must include `X-CSRF-Token` containing the token returned at login. Server-to-server bearer sessions do not require the cookie CSRF mechanism.
