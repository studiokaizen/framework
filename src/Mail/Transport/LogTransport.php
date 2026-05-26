<?php

declare(strict_types=1);

namespace Zen\Mail\Transport;

use Zen\Mail\Message;

/**
 * Mail transport that writes the full MIME message to a log file instead of
 * sending it, useful for local development.
 */
class LogTransport implements TransportInterface
{
    /**
     * Absolute path to the mail log file.
     *
     * @param  string $path
     *
     * @return void
     */
    public function __construct(private readonly string $path)
    {
    }

    /**
     * Appends the rendered MIME message to the log file wrapped in a
     * separator line, creating the log directory if necessary.
     *
     * @param  Message $message
     *
     * @return void
     */
    public function send(Message $message): void
    {
        $dir = dirname($this->path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $separator = str_repeat('=', 72);
        $entry     = PHP_EOL . $separator . PHP_EOL
                   . $message->buildMime()
                   . PHP_EOL . $separator . PHP_EOL;

        file_put_contents($this->path, $entry, FILE_APPEND | LOCK_EX);
    }
}
