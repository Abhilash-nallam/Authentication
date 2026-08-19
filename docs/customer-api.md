# Customer Platform API

Customer Dashboard A manages the customer's account, projects/apps, domain ownership, subdomain reservation and API credentials.

## Account

`POST /api/v1/customer/register`

```json
{"email":"owner@example.com","password":"at-least-10-characters"}
```

The account remains pending until the verification email is completed. Registration uses a generic success response so the public endpoint does not reveal whether an email is already registered.

`POST /api/v1/customer/verify-email`

```json
{"token":"verification-token"}
```

`POST /api/v1/customer/login`

Returns an HTTP-only session cookie and a CSRF token for browser state changes. Failed login responses are intentionally generic.

`POST /api/v1/customer/logout`

## Projects / Apps

`POST /api/v1/customer/projects` — list the authenticated customer's projects.

`POST /api/v1/customer/project-create`

```json
{"name":"SlotCare Production"}
```

A project is the current canonical backend representation of a customer app.

## Domain verification

`POST /api/v1/customer/domain-start`

```json
{"project_id":1,"domain":"slotcare.com"}
```

The response provides a unique TXT value such as `otp-auth-verification=<unique-token>`.

`POST /api/v1/customer/domain-verify`

```json
{"project_id":1}
```

The backend performs the DNS TXT lookup. Browser-supplied verification results are never trusted.

## OTP-Auth subdomain

After domain verification:

`POST /api/v1/customer/subdomain`

```json
{"project_id":1,"subdomain":"slotcare"}
```

The application reserves `slotcare.<PLATFORM_DOMAIN>`. During development `PLATFORM_DOMAIN` can be the current public hosting domain. After `otp-auth.com` is purchased and deployed, set `PLATFORM_DOMAIN=otp-auth.com` and the same project becomes `slotcare.otp-auth.com`.

The PHP application only reserves the name. Actual DNS/hosting routing remains an infrastructure deployment responsibility.

## API keys

`POST /api/v1/customer/key-create`

```json
{"project_id":1,"name":"Production server","environment":"production"}
```

The plaintext key is returned only once. Production deployments reject `test` environment keys.

`POST /api/v1/customer/keys` lists metadata only.

`POST /api/v1/customer/key-rotate`

```json
{"project_id":1,"old_key_id":123,"name":"Production rotated key","environment":"production"}
```

Rotation revokes the old key and creates the new key in one transaction.

`POST /api/v1/customer/key-revoke`

```json
{"project_id":1,"key_id":123}
```

Revocation immediately prevents API authentication.

API keys can also be restricted by IP/CIDR, browser Origin, endpoint action and OTP purpose when those controls are configured.

## Browser security

When a browser session cookie is used, state-changing customer requests must include `X-CSRF-Token` containing the token returned at login. Server-to-server bearer sessions do not use the cookie CSRF mechanism.
