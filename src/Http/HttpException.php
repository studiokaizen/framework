<?php

declare(strict_types=1);

namespace Zen\Http;

use RuntimeException;

/**
 * An exception that carries an HTTP status code, allowing the kernel to
 * convert it into an appropriate HTTP response.
 */
class HttpException extends RuntimeException
{
    /**
     * Creates the exception with the given HTTP status code and message.
     *
     * @param  int             $statusCode
     * @param  string          $message
     * @param  \Throwable|null $previous
     *
     * @return void
     */
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Returns the HTTP status code associated with this exception.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
