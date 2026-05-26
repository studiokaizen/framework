<?php

declare(strict_types=1);

namespace Zen\Routing;

/**
 * A route collection scoped to a URI prefix and a shared set of middleware,
 * delegating actual registration to the parent Router.
 */
class RouteGroup
{
    /**
     * Creates the group with a prefix, shared middleware, and the parent router.
     *
     * @param  string             $prefix
     * @param  array<int, mixed>  $middleware
     * @param  Router             $router
     *
     * @return void
     */
    public function __construct(
        private readonly string $prefix,
        private readonly array $middleware,
        private readonly Router $router,
    )
    {
    }

    /**
     * Registers a GET route within this group.
     *
     * @param  string $pattern
     * @param  mixed  $callable
     *
     * @return Route
     */
    public function get(string $pattern, mixed $callable): Route
    {
        return $this->map(['GET'], $pattern, $callable);
    }

    /**
     * Registers a POST route within this group.
     *
     * @param  string $pattern
     * @param  mixed  $callable
     *
     * @return Route
     */
    public function post(string $pattern, mixed $callable): Route
    {
        return $this->map(['POST'], $pattern, $callable);
    }

    /**
     * Registers a PUT route within this group.
     *
     * @param  string $pattern
     * @param  mixed  $callable
     *
     * @return Route
     */
    public function put(string $pattern, mixed $callable): Route
    {
        return $this->map(['PUT'], $pattern, $callable);
    }

    /**
     * Registers a PATCH route within this group.
     *
     * @param  string $pattern
     * @param  mixed  $callable
     *
     * @return Route
     */
    public function patch(string $pattern, mixed $callable): Route
    {
        return $this->map(['PATCH'], $pattern, $callable);
    }

    /**
     * Registers a DELETE route within this group.
     *
     * @param  string $pattern
     * @param  mixed  $callable
     *
     * @return Route
     */
    public function delete(string $pattern, mixed $callable): Route
    {
        return $this->map(['DELETE'], $pattern, $callable);
    }

    /**
     * Registers an OPTIONS route within this group.
     *
     * @param  string $pattern
     * @param  mixed  $callable
     *
     * @return Route
     */
    public function options(string $pattern, mixed $callable): Route
    {
        return $this->map(['OPTIONS'], $pattern, $callable);
    }

    /**
     * Registers a route for all common HTTP methods within this group.
     *
     * @param  string $pattern
     * @param  mixed  $callable
     *
     * @return Route
     */
    public function any(string $pattern, mixed $callable): Route
    {
        return $this->map(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $pattern, $callable);
    }

    /**
     * Registers a multi-method route, prepending the group prefix and
     * applying the group's shared middleware.
     *
     * @param  array<int, string> $methods
     * @param  string             $pattern
     * @param  mixed              $callable
     *
     * @return Route
     */
    public function map(array $methods, string $pattern, mixed $callable): Route
    {
        $route = $this->router->map($methods, $this->prefix . $pattern, $callable);

        if ($this->middleware !== []) {
            $route->middleware(...$this->middleware);
        }

        return $route;
    }

    /**
     * Creates a nested route group by combining prefixes and merging
     * middleware lists.
     *
     * @param  string             $prefix
     * @param  callable           $callback
     * @param  array<int, mixed>  $middleware
     *
     * @return static
     */
    public function group(string $prefix, callable $callback, array $middleware = []): static
    {
        $group = new static(
            $this->prefix . $prefix,
            array_merge($this->middleware, $middleware),
            $this->router,
        );

        $callback($group);

        return $group;
    }
}
