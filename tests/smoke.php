<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use OtpAuth\Config;
use OtpAuth\Validation;

assert(Config::int('OTP_LENGTH', 6) >= 4);
assert(Config::int('OTP_TTL_SECONDS', 300) > 0);
assert(Config::int('OTP_MAX_VERIFY_ATTEMPTS', 5) > 0);
assert(Config::int('OTP_MAX_VERIFY_REQUESTS_PER_HOUR', 20) > 0);

// Validation helpers return normalized values for valid input.
assert(Validation::email(' Test@Example.COM ') === 'test@example.com');
assert(Validation::purpose('registration') === 'registration');
assert(Validation::requestId('550e8400-e29b-41d4-a716-446655440000') === '550e8400-e29b-41d4-a716-446655440000');

// A malformed request ID must be rejected by the HTTP response layer, so this
// smoke test only exercises the valid-path contract without requiring MySQL/SES.

echo "API contract smoke test passed.\n";
