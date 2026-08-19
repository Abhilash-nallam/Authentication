# OTP Auth API — V1 Contract

## Base URL

Local development:

`http://localhost:8080`

The current repository keeps the `/api/v1` prefix. A future public hostname can be introduced without changing the contract.

## Authentication

Send the API key as:

```http
Authorization: Bearer otpa_...
Content-Type: application/json
```

API keys are secrets and must only be used by trusted server-side integrations. Never expose a secret API key in browser JavaScript.

Production deployments accept only `production` environment keys. `test` keys remain for non-production environments.

## Request OTP

`POST /api/v1/otp/request`

```json
{"email":"recipient@example.com","purpose":"registration"}
```

Success (`201`):

```json
{"success":true,"message":"OTP sent.","request_id":"550e8400-e29b-41d4-a716-446655440000","expires_at":"2026-08-19T12:00:00+00:00"}
```

Supported purposes:

- `registration`
- `login`
- `password_reset`
- `generic`

A new OTP invalidates the previous active challenge for the same API key, email, and purpose.

## Verify OTP

`POST /api/v1/otp/verify`

Preferred request:

```json
{"email":"recipient@example.com","purpose":"registration","otp":"123456","request_id":"550e8400-e29b-41d4-a716-446655440000"}
```

`request_id` is optional for backward compatibility, but clients should send it. When supplied, verification is bound to that exact OTP challenge.

Verification accepts only a successfully sent, unconsumed challenge. Concurrent verification attempts cannot consume the same OTP twice.

## Resend OTP

`POST /api/v1/otp/resend`

```json
{"email":"recipient@example.com","purpose":"registration"}
```

Resend applies both a cooldown and an hourly per-recipient limit. A successful resend creates a new request ID and invalidates the previous challenge.

## Stable error contract

Errors use this shape:

```json
{"success":false,"error":{"code":"invalid_otp","message":"Invalid OTP."}}
```

Current codes:

| HTTP | Code | Meaning |
| --- | --- | --- |
| 401 | `api_key_required` | Bearer API key is missing |
| 401 | `invalid_api_key` | API key is invalid or inactive |
| 403 | `api_key_scope_denied` | API key is not permitted to use the requested operation |
| 422 | `invalid_email` | Email is missing or invalid |
| 422 | `invalid_purpose` | Unsupported OTP purpose |
| 422 | `invalid_otp_format` | OTP format is invalid |
| 422 | `invalid_request_id` | Request ID is not a valid UUID v4 |
| 429 | `rate_limit_exceeded` | General API request limit reached |
| 429 | `verify_rate_limit_exceeded` | Verification request limit reached |
| 429 | `resend_cooldown` | Resend cooldown is still active |
| 429 | `resend_rate_limit_exceeded` | Hourly resend limit reached |
| 400 | `invalid_otp` | OTP does not match |
| 400 | `otp_expired` | OTP has expired |
| 429 | `otp_attempts_exceeded` | Maximum attempts reached |
| 502 | `otp_delivery_failed` | SES could not accept the email |
| 404 | `unknown_action` | Unsupported API action |

Clients should branch on `error.code`, not on human-readable message text.

## Security rules

- OTP values are never returned by the API.
- API keys are never returned after their initial creation.
- OTP challenges expire and are consumed after successful verification.
- A delivery-failed challenge cannot be verified.
- Verification is rate limited independently from OTP sending.
- API-key IP/origin/endpoint/purpose restrictions are enforced when configured.
- Logs must not contain OTP values or API key secrets.
- Production deployments must use HTTPS and a securely configured `APP_KEY`.

## Retry/idempotency rule

The current V1 request endpoint does not provide an idempotency-key contract. Clients should not blindly retry a successful or unknown-outcome request because a retry can issue a new OTP and invalidate the previous challenge.

If stronger retry semantics are required, an explicit `Idempotency-Key` contract will be added in a later API revision rather than inferred from request bodies.
