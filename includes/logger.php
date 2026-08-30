<?php
/**
 * Minimal append-only logger. Writes to logs/app.log.
 */

if (!defined('APP_LOG_FILE')) {
    define('APP_LOG_FILE', dirname(__DIR__) . '/logs/app.log');
}

function app_log(string $level, string $context, string $message, array $data = []): void
{
    $dir = dirname(APP_LOG_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    $line = sprintf(
        "[%s] [%s] [%s] %s%s\n",
        (new DateTimeImmutable('now', new DateTimeZone('America/Los_Angeles')))->format('Y-m-d H:i:s T'),
        str_pad(strtoupper($level), 6),
        str_pad(strtoupper($context), 12),
        $message,
        $data ? ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
    );

    file_put_contents(APP_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}
