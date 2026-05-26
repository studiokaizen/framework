<?php

declare(strict_types=1);

namespace Zen\Routing;

use Zen\Http\HttpException;

/**
 * Thrown by the router when the URI matches a route but the HTTP method is
 * not allowed, producing a 405 Method Not Allowed response.
 */
class MethodNotAllowedException extends HttpException
{
    /**
     * Creates the exception with the list of allowed methods.
     *
     * @param  array<int, string> $allowed
     * @param  string             $message
     * @param  \Throwable|null    $previous
     *
     * @return void
     */
    public function __construct(
        private readonly array $allowed,
        string $message = 'Method Not Allowed',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(405, $message, $previous);
    }

    /**
     * Returns the HTTP methods that are permitted for the matched URI.
     *
     * @return array<int, string>
     */
    public function getAllowed(): array
    {
        return $this->allowed;
    }
}
