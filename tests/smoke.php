<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use OtpAuth\Config;
use OtpAuth\Validation;

$check = static function (string $name, bool $condition): void {
    if (!$condition) {
        throw new RuntimeException("Smoke test failed: {$name}");
    }
};

$check('OTP length is valid', Config::int('OTP_LENGTH', 6) >= 4);
$check('OTP TTL is positive', Config::int('OTP_TTL_SECONDS', 300) > 0);
$check('OTP verify attempts are positive', Config::int('OTP_MAX_VERIFY_ATTEMPTS', 5) > 0);
$check('OTP hourly request limit is positive', Config::int('OTP_MAX_VERIFY_REQUESTS_PER_HOUR', 20) > 0);

// Validation helpers return normalized values for valid input.
$check('email normalization', Validation::email(' Test@Example.COM ') === 'test@example.com');
$check('purpose validation', Validation::purpose('registration') === 'registration');
$check('request ID validation', Validation::requestId('550e8400-e29b-41d4-a716-446655440000') === '550e8400-e29b-41d4-a716-446655440000');

// A malformed request ID must be rejected by the HTTP response layer, so this
// smoke test only exercises the valid-path contract without requiring MySQL/SES.
echo "API contract smoke test passed.\n";
