<?php

declare(strict_types=1);

namespace Zen\Http\Client;

use RuntimeException;
use Throwable;

/**
 * Thrown when an outgoing HTTP request fails at the transport or protocol
 * level, optionally carrying the HTTP status code and response object.
 */
class RequestException extends RuntimeException
{
    /**
     * Creates the exception with an optional status code and response.
     *
     * @param  string           $message
     * @param  int              $statusCode
     * @param  HttpResponse|null $response
     * @param  Throwable|null   $previous
     *
     * @return void
     */
    public function __construct(
        string                   $message,
        private readonly int     $statusCode = 0,
        private readonly ?HttpResponse $response = null,
        ?Throwable               $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Returns the HTTP status code, or 0 for transport-level errors.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Returns the HTTP response that triggered this exception, if any.
     *
     * @return HttpResponse|null
     */
    public function getResponse(): ?HttpResponse
    {
        return $this->response;
    }
}
