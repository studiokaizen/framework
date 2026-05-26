<?php

declare(strict_types=1);

namespace Zen\Logging;

/**
 * Simple file-based PSR-inspired logger that appends timestamped lines to a
 * single log file.
 */
class Logger
{
    /**
     * Absolute path to the log file.
     *
     * @param  string $path
     *
     * @return void
     */
    public function __construct(private readonly string $path)
    {
    }

    /**
     * Logs a DEBUG-level message.
     *
     * @param  string               $message Log message.
     * @param  array<string, mixed> $context Optional context data serialised
     *                                       as JSON after the message.
     *
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->write('DEBUG', $message, $context);
    }

    /**
     * Logs an INFO-level message.
     *
     * @param  string               $message Log message.
     * @param  array<string, mixed> $context Optional context data.
     *
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /**
     * Logs a WARNING-level message.
     *
     * @param  string               $message Log message.
     * @param  array<string, mixed> $context Optional context data.
     *
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    /**
     * Logs an ERROR-level message.
     *
     * @param  string               $message Log message.
     * @param  array<string, mixed> $context Optional context data.
     *
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /**
     * Formats a log line and appends it to the log file, creating the
     * directory if it does not exist.
     *
     * @param  string               $level   Log level label.
     * @param  string               $message Log message.
     * @param  array<string, mixed> $context Context data appended as JSON.
     *
     * @return void
     */
    private function write(string $level, string $message, array $context): void
    {
        $dir = dirname($this->path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($context !== []) {
            $message .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $line = sprintf('[%s] %s: %s', date('Y-m-d H:i:s'), $level, $message) . PHP_EOL;

        file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }
}
