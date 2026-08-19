<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

if (!is_dir(__DIR__ . '/storage')) {
    mkdir(__DIR__ . '/storage', 0750, true);
}

date_default_timezone_set('UTC');
