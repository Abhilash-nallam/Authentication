# Production Test Plan

## Infrastructure
- [ ] PHP 8.2+ runtime
- [ ] Composer dependencies installed
- [ ] MySQL database created
- [ ] `.env` configured through a secure secrets mechanism
- [ ] AWS region selected
- [ ] SES sender identity verified
- [ ] SES production access/quota confirmed

## OTP
- [ ] OTP generated with configured length
- [ ] OTP stored only as a keyed hash
- [ ] OTP expires after configured TTL
- [ ] New OTP invalidates the previous challenge
- [ ] Wrong OTP increments attempt count
- [ ] Maximum attempts enforced
- [ ] Correct OTP is consumed
- [ ] Resend cooldown works

## Flows
- [ ] Registration verification
- [ ] Login verification
- [ ] Password reset verification
- [ ] Generic verification

## Email
- [ ] Real email reaches test recipient
- [ ] Subject is correct
- [ ] OTP is clearly visible
- [ ] Expiration is shown
- [ ] Sender is verified in SES

## Security
- [ ] HTTPS enabled
- [ ] AWS secret keys not in frontend
- [ ] API keys stored hashed
- [ ] Rate limiting tested
- [ ] Logs do not contain OTP values
- [ ] Logs do not contain API keys
- [ ] Database credentials are not committed
- [ ] Debug mode disabled in production

## Launch gate
The project decision document says to purchase `otp-auth.com` only after the production test succeeds.
