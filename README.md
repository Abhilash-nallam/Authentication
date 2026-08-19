# OTP Auth — Phase 1

This implementation follows the agreed project plan: PHP backend, Amazon SES for transactional email, AWS SDK for PHP, API-based OTP generation/verification, resend/rate limiting, registration/login/password-reset flows, API keys, logging, and a basic dashboard.

Source decision document:
`OTP_Auth_Project_Decision_and_Implementation_Plan.docx`

## Important implementation assumptions

The decision document leaves the database, hosting provider, AWS region, final dashboard UI, pricing, and some production configuration open. This starter therefore uses:
- PHP 8.2+
- MySQL 8+
- PDO
- Composer
- AWS SDK for PHP
- phpdotenv
- Session-based dashboard authentication
- SHA-256 hashed API keys
- Database-backed OTP records

These are implementation choices, not decisions recorded in the source document.

## 1. Install

```bash
composer install
cp .env.example .env
```

Create a database and run:

```bash
mysql -u root -p otp_auth < database/schema.sql
```

Generate an application API key:

```bash
php bin/create_api_key.php "Test Application"
```

Put the displayed API key into the client application. The plaintext key is shown only once.

## 2. Configure AWS SES

Set AWS credentials in `.env` or, preferably in production, use an IAM role / workload identity supported by the hosting environment.

Before production:
1. Verify a test sender identity in SES.
2. Request production access if the AWS account is still in the SES sandbox.
3. Configure the final AWS region.
4. After `otp-auth.com` is purchased, configure DNS authentication records.
5. Verify the domain in SES.
6. Change `SES_FROM_EMAIL` to `otp@otp-auth.com` or `no-reply@otp-auth.com`.

Never put AWS secret keys in JavaScript or HTML.

## 3. Run locally

```bash
php -S localhost:8080 -t public
```

Dashboard:
`http://localhost:8080/`

API:
`POST /api/v1/otp/request`
`POST /api/v1/otp/verify`
`POST /api/v1/otp/resend`

## 4. API authentication

Send:

```http
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json
```

Example request:

```json
{
  "email": "recipient@example.com",
  "purpose": "registration"
}
```

Purpose values:
- registration
- login
- password_reset
- generic

Verify:

```json
{
  "email": "recipient@example.com",
  "purpose": "registration",
  "otp": "123456"
}
```

## 5. Production-test sequence

- PHP + SES integration
- OTP generation
- Secure OTP storage
- OTP expiration
- Verification
- Resend
- Rate limiting
- Registration verification
- Login verification
- Password reset verification
- API keys
- Security controls
- Clear responses
- Professional email template
- Logging
- Real email delivery
- End-to-end testing

Do not purchase the domain until the production test succeeds, per the project decision document.

## 6. Security notes

The OTP itself is not stored in plaintext. A keyed HMAC is stored using `APP_KEY`-derived material. Failed verification attempts are counted and old OTPs are invalidated when a new OTP is issued.

For a real public launch, add HTTPS, secure cookies, a managed secrets mechanism, centralized logs/metrics, database backups, alerting, WAF/DDoS controls where appropriate, and formal security testing.

## 7. Planned domain structure

After purchase and DNS/SES setup:

- `otp-auth.com`
- `api.otp-auth.com`
- `otp@otp-auth.com`
- `no-reply@otp-auth.com`
- `reset@otp-auth.com`
- `support@otp-auth.com`

Phase 2 and Phase 3 remain Coming Soon because their detailed scope is intentionally not finalized.
