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
}
