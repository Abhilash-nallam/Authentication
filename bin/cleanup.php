<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use OtpAuth\Database;

$db=Database::connection();
$otpDays=max(1,min(365,(int)($_ENV['OTP_RETENTION_DAYS'] ?? 7)));
$rateDays=max(1,min(30,(int)($_ENV['RATE_LIMIT_RETENTION_DAYS'] ?? 2)));
$sessionDays=max(1,min(30,(int)($_ENV['SESSION_RETENTION_DAYS'] ?? 2)));

$queries=[
 'otp_challenges'=>"DELETE FROM otp_challenges WHERE expires_at < UTC_TIMESTAMP() - INTERVAL {$otpDays} DAY",
 'rate_limits'=>"DELETE FROM rate_limits WHERE window_started_at < UTC_TIMESTAMP() - INTERVAL {$rateDays} DAY",
 'sessions'=>"DELETE FROM customer_sessions WHERE expires_at < UTC_TIMESTAMP() - INTERVAL {$sessionDays} DAY",
];
foreach($queries as $name=>$sql){$db->exec($sql); echo $name.': '.$db->rowCount()." deleted\n";}
