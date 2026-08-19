<?php
declare(strict_types=1);

namespace OtpAuth;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            Config::env('DB_HOST', '127.0.0.1'),
            Config::env('DB_PORT', '3306'),
            Config::env('DB_NAME', 'otp_auth'),
            Config::env('DB_CHARSET', 'utf8mb4')
        );

        try {
            self::$pdo = new PDO($dsn, Config::env('DB_USER', 'root'), Config::env('DB_PASSWORD', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed.', 0, $e);
        }

        return self::$pdo;
    }
}
