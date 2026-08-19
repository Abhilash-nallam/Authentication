Implement OTP-Auth with the following hosting architecture and assumptions.

## CURRENT HOSTING ARCHITECTURE

The primary domain will be:

optauth.com

The domain may be purchased from any legitimate low-cost .com registrar.

IMPORTANT:
Do NOT make Cloudflare mandatory.

Cloudflare is optional. The domain registrar's DNS can be used directly to point optauth.com to the current hosting provider.

Current hosting:
- InfinityFree
- PHP
- MySQL
- HTTPS/SSL where supported
- File Manager/FTP deployment
- No SSH/terminal dependency on the hosting server

The current goal is development, testing, and early-stage production at minimal cost.

Do NOT design the application around AWS-only features or Cloudflare-only features.

The application must remain portable so it can later be moved to paid hosting or AWS without rewriting the core application.

---

## DOMAIN

The main website is:

optauth.com

Potential future subdomains:

www.optauth.com
api.optauth.com
docs.optauth.com
cdn.optauth.com

Do not assume these subdomains are available until DNS is configured.

---

## CURRENT APPLICATION HOSTING

InfinityFree will initially host:

- OTP-Auth website
- Customer registration/login
- Customer dashboard
- Domain verification
- Subdomain management
- API
- PHP application
- MySQL database

Keep the PHP application compatible with standard PHP hosting.

Do not require:
- SSH
- Composer installed on the production server
- Docker
- Node.js server
- Redis
- AWS-specific runtime services

Composer may be used during development/building on a local computer, but the deployed application must be uploadable to normal PHP hosting.

---

## DATABASE

Use MySQL/MariaDB-compatible database design.

Store at minimum:

- customers
- customer projects
- verified domains
- domain verification tokens
- subdomains
- API credentials
- OTP records
- OTP attempts
- OTP expiration
- OTP purposes
- usage statistics
- rate-limit information
- audit logs
- email delivery status where available

Use proper foreign keys/relationships where supported.

Never store plaintext API secrets unnecessarily.

Never store plaintext OTPs if they can safely be stored as hashes.

---

## DOMAIN VERIFICATION

Customer flow:

Customer
↓
Register on optauth.com
↓
Enter website/domain
↓
Generate unique verification token
↓
Show DNS TXT instructions
↓
Customer adds TXT record using their own DNS provider
↓
Customer clicks Verify Domain
↓
OTP-Auth server performs DNS lookup
↓
Token matches
↓
Domain VERIFIED

Example:

Customer domain:

slotcare.com

TXT record:

Type: TXT
Host: @
Value: otp-auth-verification=UNIQUE_TOKEN

The customer does NOT need to use Cloudflare.

They may use:
- Cloudflare DNS
- GoDaddy
- Namecheap
- Hostinger
- Route 53
- Any other DNS provider

Verification must be performed server-side.

Do not trust a browser-provided verification result.

---

## OTP-AUTH SUBDOMAIN

After domain verification:

Customer chooses a unique shop/project name.

Example:

slotcare

OTP-Auth creates:

slotcare.otp-auth.com

The system must validate:
- allowed characters
- minimum/maximum length
- reserved names
- duplicate names
- abuse prevention

Only verified customers/projects can create production subdomains.

---

## CUSTOMER INTEGRATION

The default integration must be extremely simple.

Do NOT force normal customers to install Composer, use SSH, or execute terminal commands.

Provide:

### EASY INTEGRATION

A copy-and-paste JavaScript/widget integration.

Example concept:

<script src="https://cdn.optauth.com/widget.js"></script>

<script>
OtpAuth.init({
    project: "PUBLIC_PROJECT_ID"
});
</script>

The exact implementation must be designed securely.

Never expose a secret API key in frontend JavaScript.

The widget should support:

- Login OTP
- Signup/email verification
- Password reset
- Email change verification
- Other authentication/verification use cases

The developer should be able to integrate the basic OTP functionality with minimal code.

---

## ADVANCED INTEGRATION

Also provide a REST API for developers who want full control.

At minimum:

POST /v1/otp/send
POST /v1/otp/verify
POST /v1/otp/resend

Support:
- API authentication
- API key rotation
- API key revocation
- rate limits
- project isolation
- request validation
- error responses
- usage tracking

Later, official SDKs can be provided for:
- PHP
- Node.js
- Python

SDKs are optional, not mandatory.

---

## OTP GENERATION

Generate cryptographically secure OTPs.

Each OTP must have:
- project ID
- recipient
- purpose
- expiration
- attempt limit
- verification status

Prevent:
- brute-force attacks
- OTP reuse
- unlimited resend
- unlimited attempts

Use secure hashing/storage for OTP values.

---

## EMAIL DELIVERY

Use Amazon SES as the production email delivery service.

OTP-Auth owns and manages SES credentials.

Customers must never receive SES credentials.

The email sender should use the customer's assigned OTP-Auth subdomain.

Example:

verification@slotcare.otp-auth.com

The system should eventually support:

- SPF
- DKIM
- DMARC
- bounce monitoring
- complaint monitoring
- delivery logging

Do not promise an exact inbox delivery time because recipient mail servers and spam filtering are outside our control.

---

## PERFORMANCE

Optimize the request path:

Customer website
↓
OTP-Auth API
↓
Generate OTP
↓
Amazon SES
↓
Recipient

OTP generation and SES submission should be fast.

The user interface should say:

"OTP sent. It may take a few seconds to arrive."

Provide a secure resend option with cooldown.

---

## SECURITY

Implement:

- HTTPS
- CSRF protection where applicable
- SQL injection protection
- XSS protection
- secure session handling
- password hashing
- API authentication
- API key protection
- OTP rate limiting
- IP rate limiting
- recipient rate limiting
- project isolation
- audit logs
- domain verification
- abuse detection
- project suspension
- API key revocation

Never allow an unverified project to use production OTP services.

---

## CLOUDflare

Cloudflare is OPTIONAL.

Do not hard-code the architecture so that Cloudflare is required.

If Cloudflare is used later, it should be possible to place it in front of:

optauth.com

and/or:

api.optauth.com

without changing the application's core business logic.

The application must work without Cloudflare.

---

## MIGRATION REQUIREMENT

The current deployment is InfinityFree.

Later, the application may move to:

- paid PHP hosting
- VPS
- AWS
- another cloud provider

Therefore:

Keep:
- PHP code portable
- database portable
- environment configuration separate from application code
- email provider integration modular
- DNS logic provider-independent
- hosting-specific code to a minimum

Do not tightly couple the application to InfinityFree.

---

## DEVELOPMENT ORDER

Implement and test in this order:

1. Customer registration
2. Customer login
3. Customer dashboard
4. Project creation
5. Domain entry
6. DNS TXT verification
7. Domain verification status
8. Subdomain selection
9. Subdomain creation/management
10. API credential generation
11. OTP generation
12. OTP storage/expiration/attempt limits
13. SES integration
14. OTP email delivery
15. OTP verification
16. Easy one-snippet integration
17. Login OTP
18. Signup verification
19. Password reset OTP
20. Usage dashboard
21. Rate limiting
22. Abuse prevention
23. Advanced REST API
24. Test mode
25. Production hardening

Do not build unnecessary AWS/Cloudflare infrastructure at this stage.

The priority is to make the complete OTP-Auth product work reliably on standard PHP + MySQL hosting first.

FINAL ARCHITECTURE:

optauth.com
↓
DNS provider of choice
↓
InfinityFree
├── PHP website
├── Customer dashboard
├── PHP API
└── MySQL
↓
Amazon SES
↓
Customer's end user

Later:

optauth.com
↓
Cloudflare (optional)
↓
Paid production hosting/AWS
↓
OTP-Auth API + database
↓
Amazon SES