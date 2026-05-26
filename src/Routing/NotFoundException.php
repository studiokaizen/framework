<?php

declare(strict_types=1);

namespace Zen\Routing;

use Zen\Http\HttpException;

/**
 * Thrown by the router when no route matches the requested URI, producing a
 * 404 Not Found HTTP response.
 */
class NotFoundException extends HttpException
{
    /**
     * Creates the exception with an optional custom message.
     *
     * @param  string          $message
     * @param  \Throwable|null $previous
     *
     * @return void
     */
    public function __construct(string $message = 'Not Found', ?\Throwable $previous = null)
    {
        parent::__construct(404, $message, $previous);
    }
}
