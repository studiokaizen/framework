<?php

declare(strict_types=1);

namespace Zen\Middleware;

use Zen\Http\Request;
use Zen\Http\Response;

/**
 * Composes multiple middleware layers into a single callable pipeline that
 * wraps a core handler.
 */
class Stack
{
    /**
     * The ordered list of middleware layers to apply.
     *
     * @var array<int, MiddlewareInterface>
     */
    private array $layers = [];

    /**
     * Appends one or more middleware layers to the stack and returns it.
     *
     * @param  MiddlewareInterface ...$layers
     *
     * @return static
     */
    public function add(MiddlewareInterface ...$layers): static
    {
        array_push($this->layers, ...$layers);

        return $this;
    }

    /**
     * Runs the middleware pipeline around the given core handler and returns
     * the resulting response.
     *
     * @param  Request  $request
     * @param  Response $response
     * @param  callable $core
     *
     * @return Response
     */
    public function run(Request $request, Response $response, callable $core): Response
    {
        $handler = array_reduce(
            array_reverse($this->layers),
            static fn(callable $next, MiddlewareInterface $layer): callable
                => fn(Request $request, Response $response): Response
                    => $layer->process($request, $response, $next),
            $core,
        );

        return $handler($request, $response);
    }
}
