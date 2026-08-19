# Production Test Plan

## Infrastructure
- [ ] PHP 8.2+ runtime
- [ ] Composer dependencies installed
- [ ] MySQL database created
- [ ] Fresh schema import succeeds OR one-time Phase 1 migration succeeds
- [ ] `.env` configured through a secure secrets mechanism
- [ ] AWS region selected
- [ ] SES sender identity verified
- [ ] SES production access/quota confirmed
- [ ] Cleanup command scheduled
- [ ] Database backup and restore tested

## Customer platform
- [ ] Customer registration succeeds
- [ ] Customer verification email arrives
- [ ] Unverified customer cannot use authenticated customer operations
- [ ] Customer login creates secure HTTP-only session cookie
- [ ] Cookie-session state changes require CSRF token
- [ ] Customer can create and list only their own projects
- [ ] Project API key is isolated to its project
- [ ] API key appears only once at creation
- [ ] API key rotation revokes the old key atomically
- [ ] API key revocation blocks authentication

## Domain verification
- [ ] Customer enters a valid real domain
- [ ] Unique TXT token is generated
- [ ] TXT instructions are correct
- [ ] Backend performs DNS TXT lookup
- [ ] Browser cannot force a false VERIFIED result
- [ ] Wrong TXT value is rejected
- [ ] Expired verification token is rejected
- [ ] Same domain cannot be claimed by another project

## Subdomain
- [ ] Verified project can reserve a valid slug
- [ ] Invalid slug rejected
- [ ] Reserved names rejected
- [ ] Duplicate slug rejected
- [ ] Unverified project cannot reserve production subdomain
- [ ] DNS/hosting layer actually routes the reserved hostname before public use

## OTP
- [ ] OTP generated with configured length
- [ ] OTP stored only as a keyed hash
- [ ] OTP expires after configured TTL
- [ ] New OTP invalidates the previous challenge
- [ ] Wrong OTP increments attempt count
- [ ] Maximum attempts enforced
- [ ] Correct OTP is consumed
- [ ] Reused OTP is rejected
- [ ] Request ID challenge binding works
- [ ] Per-IP rate limit works
- [ ] Per-email rate limit works
- [ ] Per-project rate limit works
- [ ] Verification brute-force rate limit works
- [ ] Resend cooldown works
- [ ] Hourly resend limit works

## Flows
- [ ] Registration verification
- [ ] Login verification
- [ ] Password reset verification
- [ ] Generic verification

## Email / SES
- [ ] Real email reaches test recipient
- [ ] Subject is correct
- [ ] OTP is clearly visible
- [ ] Expiration is shown
- [ ] Sender is verified in SES
- [ ] Configuration set is applied if configured
- [ ] Send event is recorded
- [ ] Delivery/bounce/complaint events are observable
- [ ] SES failure does not leave an active OTP challenge behind

## Security
- [ ] HTTPS enabled
- [ ] AWS secret keys not in frontend
- [ ] API keys stored hashed
- [ ] Domain verification token is not stored plaintext
- [ ] Rate limiting tested
- [ ] Logs do not contain OTP values
- [ ] Logs do not contain API keys
- [ ] Database credentials are not committed
- [ ] Debug mode disabled in production
- [ ] Production APP_KEY is random and secret
- [ ] Backup restore succeeds

## Launch gate
The project decision document says to purchase `otp-auth.com` only after the production test succeeds.
