<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use OtpAuth\ApiKeyService;
use OtpAuth\Database;

$name = $argv[1] ?? null;
if (!$name) {
    fwrite(STDERR, "Usage: php bin/create_api_key.php \"Application name\"\n");
    exit(1);
}

$result = (new ApiKeyService(Database::connection()))->create($name);

echo "API key created.\n";
echo "ID: {$result['id']}\n";
echo "Prefix: {$result['prefix']}\n";
echo "KEY (store this now; it is not retrievable later): {$result['key']}\n";
