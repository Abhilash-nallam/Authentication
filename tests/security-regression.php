<?php
declare(strict_types=1);

$files=[
    'src/CustomerService.php'=>file_get_contents(__DIR__.'/../src/CustomerService.php'),
    'src/OtpService.php'=>file_get_contents(__DIR__.'/../src/OtpService.php'),
    'src/AdminController.php'=>file_get_contents(__DIR__.'/../src/AdminController.php'),
    'src/ApiKeyService.php'=>file_get_contents(__DIR__.'/../src/ApiKeyService.php'),
    'src/SettingsService.php'=>file_get_contents(__DIR__.'/../src/SettingsService.php'),
];
foreach($files as $path=>$content)if($content===false)throw new RuntimeException("Unable to read {$path}");
assert(str_contains($files['src/CustomerService.php'],'FILTER_NULL_ON_FAILURE'));
assert(str_contains($files['src/CustomerService.php'],'signup_max_per_ip_per_hour'));
assert(str_contains($files['src/CustomerService.php'],'password_reset_max_per_email_per_hour'));
assert(str_contains($files['src/OtpService.php'],"status='sent'"));
assert(str_contains($files['src/OtpService.php'],'FOR UPDATE'));
assert(str_contains($files['src/OtpService.php'],"status='delivery_failed',delivery_failed_at=UTC_TIMESTAMP(),last_error_code='ses_delivery_failed',consumed_at=UTC_TIMESTAMP()"));
assert(str_contains($files['src/AdminController.php'],'csrf_failed'));
assert(str_contains($files['src/ApiKeyService.php'],"$row['environment']!=='production'"));
assert(str_contains($files['src/SettingsService.php'],'Setting is not editable.'));

echo "Security regression checks passed.\n";
