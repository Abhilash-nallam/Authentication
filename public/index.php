<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use OtpAuth\AbuseLimiter;use OtpAuth\AdminController;use OtpAuth\AdminService;use OtpAuth\ApiController;use OtpAuth\ApiKeyService;use OtpAuth\Config;use OtpAuth\CustomerController;use OtpAuth\CustomerMailer;use OtpAuth\CustomerService;use OtpAuth\Database;use OtpAuth\DomainVerificationService;use OtpAuth\OtpService;use OtpAuth\ProjectService;use OtpAuth\RateLimiter;use OtpAuth\Response;use OtpAuth\SesEventController;use OtpAuth\SettingsService;
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
try{
 Config::assertProductionSafe();$db=Database::connection();$settings=new SettingsService($db);$abuse=new AbuseLimiter($db);
 if(str_starts_with($path,'/api/v1/otp/'))(new ApiController(new ApiKeyService($db),new RateLimiter($db),$abuse,$settings,new OtpService($db)))->handle(trim(substr($path,strlen('/api/v1/otp/')),'/'));
 if(str_starts_with($path,'/api/v1/customer/'))(new CustomerController(new CustomerService($db,$abuse),new CustomerMailer(),new ProjectService($db),new DomainVerificationService($db),new ApiKeyService($db)))->handle(trim(substr($path,strlen('/api/v1/customer/')),'/'));
 if(str_starts_with($path,'/api/v1/admin/'))(new AdminController(new AdminService($db,$abuse),$settings))->handle(trim(substr($path,strlen('/api/v1/admin/')),'/'));
 if($path==='/api/v1/ses/events')(new SesEventController($db))->handle();
 if($path==='/health')Response::success(['service'=>'otp-auth','time'=>gmdate(DATE_ATOM)]);
 if($path==='/verify-email'){$ok=(new CustomerService($db,$abuse))->verifyEmail((string)($_GET['token']??''));header('Content-Type: text/html; charset=utf-8');header('Referrer-Policy: no-referrer');echo $ok?'<h1>Email verified</h1><p>Your OTP Auth account is active. You can return to the dashboard.</p>':'<h1>Verification failed</h1><p>The verification link is invalid or expired.</p>';exit;}
 if($path==='/reset-password'){require __DIR__.'/reset-password.php';exit;}
 if($path==='/admin'||$path==='/admin/') {require __DIR__.'/admin.php';exit;}
 if($path==='/'||$path==='/index.php'){require __DIR__.'/dashboard.php';exit;}
 Response::error('not_found','Not found.',404);
}catch(Throwable $e){error_log($e->getMessage());Response::error('internal_server_error',Config::bool('APP_DEBUG')?$e->getMessage():'Internal server error.',500);}
