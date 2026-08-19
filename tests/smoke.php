<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use OtpAuth\Config;

assert(Config::int('OTP_LENGTH', 6) >= 4);
assert(Config::int('OTP_TTL_SECONDS', 300) > 0);

echo "Basic configuration smoke test passed.\n";
