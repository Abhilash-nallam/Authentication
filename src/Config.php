<?php
declare(strict_types=1);

namespace OtpAuth;

final class Config
{
    public static function env(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        return ($value === null || $value === '') ? $default : $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return filter_var(self::env($key, $default ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function int(string $key, int $default): int
    {
        return (int) self::env($key, (string)$default);
    }

    public static function requireSecret(string $key, int $minimumLength = 32): string
    {
        $value = self::env($key);
        if ($value === null || strlen($value) < $minimumLength) {
            throw new \RuntimeException($key . ' must be configured with at least ' . $minimumLength . ' characters.');
        }
        return $value;
    }

    public static function assertProductionSafe(): void
    {
        if (self::env('APP_ENV', 'development') !== 'production') {
            return;
        }

        if (self::bool('APP_DEBUG', false)) {
            throw new \RuntimeException('APP_DEBUG must be false in production.');
        }

        self::requireSecret('APP_KEY');

        $from = self::env('SES_FROM_EMAIL');
        if (!$from || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('SES_FROM_EMAIL must be configured with a valid verified sender in production.');
        }
    }
}
