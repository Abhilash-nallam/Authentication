<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use OtpAuth\Database;

$db=Database::connection();
$otpDays=max(1,min(365,(int)($_ENV['OTP_RETENTION_DAYS']??7)));
$rateDays=max(1,min(30,(int)($_ENV['RATE_LIMIT_RETENTION_DAYS']??2)));
$sessionDays=max(1,min(30,(int)($_ENV['SESSION_RETENTION_DAYS']??2)));
$queries=[
 'otp_challenges'=>"DELETE FROM otp_challenges WHERE expires_at < UTC_TIMESTAMP() - INTERVAL {$otpDays} DAY",
 'rate_limits'=>"DELETE FROM rate_limits WHERE window_started_at < UTC_TIMESTAMP() - INTERVAL {$rateDays} DAY",
 'abuse_limits'=>"DELETE FROM abuse_limits WHERE window_started_at < UTC_TIMESTAMP() - INTERVAL {$rateDays} DAY",
 'sessions'=>"DELETE FROM customer_sessions WHERE expires_at < UTC_TIMESTAMP() - INTERVAL {$sessionDays} DAY",
 'admin_sessions'=>"DELETE FROM admin_sessions WHERE expires_at < UTC_TIMESTAMP() - INTERVAL {$sessionDays} DAY",
 'otp_events'=>"DELETE FROM otp_events WHERE created_at < UTC_TIMESTAMP() - INTERVAL {$otpDays} DAY",
 'email_events'=>"DELETE FROM email_events WHERE created_at < UTC_TIMESTAMP() - INTERVAL {$otpDays} DAY",
 'ses_events'=>"DELETE FROM ses_events WHERE created_at < UTC_TIMESTAMP() - INTERVAL {$otpDays} DAY",
];
foreach($queries as $name=>$sql){try{$db->exec($sql);echo $name.': '.$db->rowCount()." deleted\n";}catch(Throwable $e){echo $name.': skipped (migration not applied)\n';}}
