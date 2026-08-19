<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use OtpAuth\Database;

$db=Database::connection();
$otpDays=(int)($_ENV['OTP_RETENTION_DAYS'] ?? 7);
$rateDays=(int)($_ENV['RATE_LIMIT_RETENTION_DAYS'] ?? 2);
$sessionDays=(int)($_ENV['SESSION_RETENTION_DAYS'] ?? 2);

$deleted=[];
$deleted['otp_challenges']=$db->prepare('DELETE FROM otp_challenges WHERE expires_at < UTC_TIMESTAMP() - INTERVAL ? DAY');
$deleted['otp_challenges']->execute([$otpDays]);
$deleted['rate_limits']=$db->prepare('DELETE FROM rate_limits WHERE window_started_at < UTC_TIMESTAMP() - INTERVAL ? DAY');
$deleted['rate_limits']->execute([$rateDays]);
$deleted['sessions']=$db->prepare('DELETE FROM customer_sessions WHERE expires_at < UTC_TIMESTAMP() - INTERVAL ? DAY');
$deleted['sessions']->execute([$sessionDays]);

echo "Cleanup completed.\n";
