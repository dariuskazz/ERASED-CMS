<?php
declare(strict_types=1);

namespace Erased\Security;

final class SecurityLogger
{
    private const MAX_LOG_SIZE = 5242880;

    public function __construct(private readonly ?string $logFile = null)
    {
    }

    public function log(string $event, array $context = [], string $level = 'info'): void
    {
        $file = $this->logFile ?? dirname(__DIR__, 2) . '/storage/logs/security.log';
        $directory = dirname($file);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        $this->rotateIfNeeded($file);
        $level = in_array($level, ['debug', 'info', 'notice', 'warning', 'error', 'critical'], true)
            ? $level
            : 'info';

        $record = [
            'time' => gmdate('c'),
            'level' => $level,
            'event' => preg_replace('/[^a-zA-Z0-9._-]/', '_', $event) ?: 'unknown',
            'request_id' => $_SERVER['ERASED_REQUEST_ID'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => isset($_SERVER['REQUEST_URI']) ? substr((string) $_SERVER['REQUEST_URI'], 0, 1000) : null,
            'user_id' => $_SESSION['user_id'] ?? null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
            'context' => $this->sanitizeContext($context),
        ];

        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if (is_string($line)) {
            @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    private function rotateIfNeeded(string $file): void
    {
        if (!is_file($file) || (int) @filesize($file) < self::MAX_LOG_SIZE) {
            return;
        }
        $rotated = $file . '.' . gmdate('Ymd-His');
        @rename($file, $rotated);
        foreach (glob($file . '.*') ?: [] as $old) {
            if (@filemtime($old) !== false && @filemtime($old) < time() - 2592000) {
                @unlink($old);
            }
        }
    }

    private function sanitizeContext(array $context): array
    {
        $blocked = ['password', 'pass', 'token', 'csrf', 'secret', 'authorization', 'cookie'];
        $clean = [];
        foreach ($context as $key => $value) {
            $normalized = strtolower((string) $key);
            $clean[$key] = in_array($normalized, $blocked, true)
                ? '[REDACTED]'
                : (is_string($value) ? mb_substr($value, 0, 2000, 'UTF-8') : $value);
        }
        return $clean;
    }
}
