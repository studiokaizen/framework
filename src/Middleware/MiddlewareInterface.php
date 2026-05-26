<?php

declare(strict_types=1);

namespace Zen\Middleware;

use Zen\Http\Request;
use Zen\Http\Response;

/**
 * Contract for all request/response middleware layers that participate in the
 * middleware stack.
 */
interface MiddlewareInterface
{
    /**
     * Processes the request, optionally calling the next layer, and returns a
     * response.
     *
     * @param  Request  $request
     * @param  Response $response
     * @param  callable $next
     *
     * @return Response
     */
    public function process(Request $request, Response $response, callable $next): Response;
}
