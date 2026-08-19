<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use OtpAuth\Config;
use OtpAuth\Database;
use OtpAuth\ApiKeyService;
use OtpAuth\RateLimiter;
use OtpAuth\OtpService;
use OtpAuth\ApiController;
use OtpAuth\Response;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (str_starts_with($path, '/api/v1/otp/')) {
    $action = trim(substr($path, strlen('/api/v1/otp/')), '/');
    try {
        $db = Database::connection();
        $controller = new ApiController(
            new ApiKeyService($db),
            new RateLimiter($db),
            new OtpService($db)
        );
        $controller->handle($action);
    } catch (Throwable $e) {
        error_log($e->getMessage());
        Response::json([
            'success' => false,
            'error' => Config::bool('APP_DEBUG') ? $e->getMessage() : 'Internal server error.'
        ], 500);
    }
}

if ($path === '/health') {
    Response::json(['success' => true, 'service' => 'otp-auth', 'time' => gmdate(DATE_ATOM)]);
}

if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/dashboard.php';
    exit;
}

Response::json(['success' => false, 'error' => 'Not found.'], 404);
