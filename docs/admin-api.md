# Admin Dashboard B API

Admin Dashboard B is the private platform control plane. It is intentionally separate from Customer Dashboard A.

## Authentication

Create the first administrator after migrations:

```bash
php bin/create_admin.php admin@example.com 'strong-password'
```

`POST /api/v1/admin/login`

```json
{"email":"admin@example.com","password":"strong-password"}
```

The session is stored in an HTTP-only cookie. The API also accepts a bearer session token for server-side tooling.

## Core endpoints

- `POST /api/v1/admin/me`
- `POST /api/v1/admin/overview`
- `POST /api/v1/admin/customers`
- `POST /api/v1/admin/customer-suspend`
- `POST /api/v1/admin/customer-reactivate`
- `POST /api/v1/admin/api-keys`
- `POST /api/v1/admin/key-revoke`
- `POST /api/v1/admin/otp-logs`
- `POST /api/v1/admin/logout`

## RBAC

The database includes roles and permissions for:

- `super_admin`
- `support_admin`
- `security_admin`
- `read_only`

Permissions include customer viewing/suspension, API-key revocation, OTP-log access, settings, SES, DNS, billing, admin management and security management.

Only permissions assigned to the authenticated administrator's role are enforced by the controller.

## Safety rules

- Admin sessions expire after eight hours.
- Five failed login attempts temporarily lock an admin account for 15 minutes.
- Customer suspension disables the customer's active API keys.
- Admin API never returns API-key plaintext values.
- OTP values are never returned by admin APIs.
