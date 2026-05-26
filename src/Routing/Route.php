<?php

declare(strict_types=1);

namespace Zen\Routing;

use Zen\Middleware\MiddlewareInterface;

/**
 * Represents a single registered route with its HTTP methods, URI pattern,
 * handler, name, and middleware list.
 */
class Route
{
    /**
     * Optional name used to generate URLs for this route.
     *
     * @var string
     */
    private string $name = '';

    /**
     * Middleware applied only to this route, in registration order.
     *
     * @var array<int, string|MiddlewareInterface>
     */
    private array $middleware = [];

    /**
     * Creates the route with the given methods, pattern, and handler.
     *
     * @param  array<int, string> $methods
     * @param  string             $pattern
     * @param  mixed              $callable
     *
     * @return void
     */
    public function __construct(
        private readonly array $methods,
        private readonly string $pattern,
        private mixed $callable,
    )
    {
    }

    /**
     * Assigns a name to this route and returns it for chaining.
     *
     * @param  string $name
     *
     * @return static
     */
    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Appends middleware to this route's middleware list and returns the route.
     *
     * @param  string|MiddlewareInterface ...$middleware
     *
     * @return static
     */
    public function middleware(string|MiddlewareInterface ...$middleware): static
    {
        array_push($this->middleware, ...$middleware);

        return $this;
    }

    /**
     * Returns the route name, or an empty string if none was set.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the list of uppercase HTTP methods this route responds to.
     *
     * @return array<int, string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * Returns the raw URI pattern string.
     *
     * @return string
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * Returns the route handler callable or invokable class.
     *
     * @return mixed
     */
    public function getCallable(): mixed
    {
        return $this->callable;
    }

    /**
     * Returns the middleware assigned to this route.
     *
     * @return array<int, string|MiddlewareInterface>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Compiles the URI pattern into a named-capture-group PCRE regex.
     *
     * Pattern tokens such as `:id`, `(:slug)`, `:rest+` and `:any*` are
     * converted to named sub-patterns. Optional segments are wrapped in
     * non-capturing optional groups.
     *
     * @return string
     */
    public function compile(): string
    {
        $pattern = preg_replace_callback(
            '/\(?\:([a-zA-Z_][a-zA-Z0-9_]*)(\+|\*)?\)?/',
            static function (array $matches): string {
                $name      = $matches[1];
                $quantifier = $matches[2] ?? '';
                $optional  = str_starts_with($matches[0], '(') && str_ends_with($matches[0], ')');

                $regex = match ($quantifier) {
                    '+'     => '(?P<' . $name . '>.+)',
                    '*'     => '(?P<' . $name . '>.*)',
                    default => '(?P<' . $name . '>[^/]+)',
                };

                return $optional ? '(?:' . $regex . ')?' : $regex;
            },
            $this->pattern,
        );

        return '#^' . $pattern . '$#u';
    }
}
