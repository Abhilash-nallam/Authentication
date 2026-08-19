<?php
declare(strict_types=1);

namespace OtpAuth;

final class Logger
{
    public static function info(string $event, array $context = []): void
    {
        self::write('INFO', $event, $context);
    }

    public static function error(string $event, array $context = []): void
    {
        self::write('ERROR', $event, $context);
    }

    private static function write(string $level, string $event, array $context): void
    {
        $dir = dirname(__DIR__) . '/storage';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $record = [
            'time' => gmdate('c'),
            'level' => $level,
            'event' => $event,
            'context' => $context,
        ];

        error_log(json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, $dir . '/app.log');
    }
}
