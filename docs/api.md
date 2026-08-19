# OTP Auth API

Base URL during local testing:

`http://localhost:8080`

Authentication:

`Authorization: Bearer otpa_...`

## Request OTP

`POST /api/v1/otp/request`

```json
{
  "email": "recipient@example.com",
  "purpose": "registration"
}
```

Success:

```json
{
  "success": true,
  "message": "OTP sent.",
  "request_id": "uuid",
  "expires_at": "2026-08-17T..."
}
```

## Verify OTP

`POST /api/v1/otp/verify`

```json
{
  "email": "recipient@example.com",
  "purpose": "registration",
  "otp": "123456"
}
```

## Resend OTP

`POST /api/v1/otp/resend`

Uses the same request body as the request endpoint and applies a resend cooldown.

## Error behavior

- 401: missing/invalid API key
- 422: invalid input
- 429: rate limit
- 400: invalid/expired/too-many-attempts OTP
- 502: SES delivery failure
- 500: unexpected server error
