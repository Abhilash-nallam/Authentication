<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use OtpAuth\ApiController;
use OtpAuth\ApiKeyService;
use OtpAuth\Config;
use OtpAuth\CustomerController;
use OtpAuth\CustomerMailer;
use OtpAuth\CustomerService;
use OtpAuth\Database;
use OtpAuth\DomainVerificationService;
use OtpAuth\OtpService;
use OtpAuth\ProjectService;
use OtpAuth\RateLimiter;
use OtpAuth\Response;
use OtpAuth\SesEventController;

$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
try {
    Config::assertProductionSafe(); $db=Database::connection();
    if(str_starts_with($path,'/api/v1/otp/')) (new ApiController(new ApiKeyService($db),new RateLimiter($db),new OtpService($db)))->handle(trim(substr($path,strlen('/api/v1/otp/')),'/'));
    if(str_starts_with($path,'/api/v1/customer/')) (new CustomerController(new CustomerService($db),new CustomerMailer(),new ProjectService($db),new DomainVerificationService($db),new ApiKeyService($db)))->handle(trim(substr($path,strlen('/api/v1/customer/')),'/'));
    if($path==='/api/v1/ses/events') (new SesEventController($db))->handle();
    if($path==='/health') Response::success(['service'=>'otp-auth','time'=>gmdate(DATE_ATOM)]);
    if($path==='/'||$path==='/index.php'){require __DIR__.'/dashboard.php';exit;}
    Response::error('not_found','Not found.',404);
} catch(Throwable $e) { error_log($e->getMessage()); Response::error('internal_server_error',Config::bool('APP_DEBUG')?$e->getMessage():'Internal server error.',500); }
