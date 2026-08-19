# Architecture

Customer Website/App
        |
        | HTTPS + Bearer API key
        v
OTP Auth API (PHP)
        |
        +--> OTP generation / hashing / expiry / verification
        |
        +--> MySQL
        |
        +--> AWS SDK for PHP
                 |
                 v
             Amazon SES
                 |
                 v
          Recipient email

After successful production testing:
- purchase otp-auth.com
- configure DNS
- configure SES domain authentication
- verify the domain
- use otp@otp-auth.com or no-reply@otp-auth.com
- deploy public API and website

The source plan explicitly keeps Phase 2 and Phase 3 scope open.
