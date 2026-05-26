<?php

declare(strict_types=1);

namespace Zen\Routing;

use RuntimeException;

/**
 * Registers route definitions and matches an incoming method/path pair to
 * the correct Route, extracting named parameters.
 */
class Router
{
    /**
     * All registered Route objects in declaration order.
     *
     * @var array<int, Route>
     */
    private array $routes = [];

    /**
     * Named routes indexed by their assigned name string.
     *
     * @var array<string, Route>
     */
    private array $named = [];

    /**
     * Registers a GET route.
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
     * Registers a POST route.
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
     * Registers a PUT route.
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
     * Registers a PATCH route.
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
     * Registers a DELETE route.
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
     * Registers an OPTIONS route.
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
     * Registers a route that responds to all common HTTP methods.
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
     * Registers a route for one or more HTTP methods.
     *
     * @param  array<int, string> $methods
     * @param  string             $pattern
     * @param  mixed              $callable
     *
     * @return Route
     */
    public function map(array $methods, string $pattern, mixed $callable): Route
    {
        $route = new Route(
            array_map('strtoupper', $methods),
            $pattern,
            $callable,
        );

        $this->routes[] = $route;

        return $route;
    }

    /**
     * Creates a route group with a shared prefix and optional middleware, then
     * passes a RouteGroup instance to the callback for registration.
     *
     * @param  string   $prefix
     * @param  callable $callback
     * @param  array<int, mixed> $middleware
     *
     * @return RouteGroup
     */
    public function group(string $prefix, callable $callback, array $middleware = []): RouteGroup
    {
        $group = new RouteGroup($prefix, $middleware, $this);

        $callback($group);

        return $group;
    }

    /**
     * Matches the given HTTP method and path against all registered routes
     * and returns a two-element array of [Route, params].
     *
     * @param  string $method
     * @param  string $path
     *
     * @throws MethodNotAllowedException If the path matches but not the method.
     * @throws NotFoundException         If no route matches the path.
     *
     * @return array{0: Route, 1: array<string, string>}
     */
    public function match(string $method, string $path): array
    {
        $method  = strtoupper($method);
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route->compile(), $path, $matches)) {
                continue;
            }

            if (!in_array($method, $route->getMethods(), true)) {
                array_push($allowed, ...$route->getMethods());
                continue;
            }

            $params = array_filter(
                $matches,
                static fn(string|int $key): bool => is_string($key),
                ARRAY_FILTER_USE_KEY,
            );

            return [$route, $params];
        }

        if ($allowed !== []) {
            throw new MethodNotAllowedException(array_unique($allowed));
        }

        throw new NotFoundException();
    }

    /**
     * Generates the URL for a named route by substituting the given
     * parameters into the pattern.
     *
     * @param  string               $name
     * @param  array<string, mixed> $params
     *
     * @throws RuntimeException If the named route does not exist or a required
     *                          parameter is missing.
     *
     * @return string
     */
    public function urlFor(string $name, array $params = []): string
    {
        if (!isset($this->named[$name])) {
            $this->indexNamedRoutes();
        }

        if (!isset($this->named[$name])) {
            throw new RuntimeException(sprintf('No route named "%s".', $name));
        }

        $pattern = $this->named[$name]->getPattern();

        $url = preg_replace_callback(
            '/\(?\:([a-zA-Z_][a-zA-Z0-9_]*)(\+|\*)?\)?/',
            static function (array $matches) use ($params): string {
                $name = $matches[1];

                if (!isset($params[$name])) {
                    $optional = str_starts_with($matches[0], '(') && str_ends_with($matches[0], ')');

                    return $optional ? '' : throw new RuntimeException(
                        sprintf('Missing required route parameter "%s".', $name),
                    );
                }

                return (string) $params[$name];
            },
            $pattern,
        );

        return '/' . ltrim($url, '/');
    }

    /**
     * Returns all registered routes.
     *
     * @return array<int, Route>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Populates the named route index from all registered routes.
     *
     * @return void
     */
    private function indexNamedRoutes(): void
    {
        foreach ($this->routes as $route) {
            if ($route->getName() !== '') {
                $this->named[$route->getName()] = $route;
            }
        }
    }
}
