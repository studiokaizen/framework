<?php

declare(strict_types=1);

namespace Zen;

use Closure;
use ErrorException;
use InvalidArgumentException;
use Zen\DependencyInjection\BootableProviderInterface;
use Zen\DependencyInjection\Container;
use Zen\DependencyInjection\ServiceProviderInterface;
use Zen\Http\Kernel;
use Zen\Http\Request;
use Zen\Http\Response;
use Zen\Middleware\MiddlewareInterface;
use Zen\Routing\Route;
use Zen\Routing\RouteGroup;
use Zen\Validation\Validator;

/**
 * The application container — bootstraps providers, middleware, routing, and
 * the HTTP kernel.
 */
class Application extends Container
{
    /**
     * Absolute path to the project root.
     *
     * @var string
     */
    private string $basePath;

    /**
     * Registered service providers.
     *
     * @var ServiceProviderInterface[]
     */
    private array $providers = [];

    /**
     * Indicates whether all bootable providers have been initialized.
     *
     * @var bool
     */
    private bool $booted = false;

    /**
     * Global middleware stack, runs on every request.
     *
     * @var array<string|MiddlewareInterface>
     */
    private array $middleware = [];

    /**
     * Alias-to-factory map for named middleware.
     *
     * @var array<string, string|Closure>
     */
    private array $middlewareAliases = [];

    /**
     * Group name to list of middleware aliases.
     *
     * @var array<string, string[]>
     */
    private array $middlewareGroups = [];

    /**
     * HTTP status code to error handler map.
     *
     * @var array<int, callable>
     */
    private array $errorHandlers = [];

    /**
     * Sets the application base path and installs the error handler.
     *
     * @param string $basePath Absolute path to the project root.
     */
    public function __construct(string $basePath)
    {
        parent::__construct();

        $this->setBasePath($basePath);
        $this->registerErrorReporting();
    }

    /**
     * Normalises and stores the project root path, stripping any trailing
     * slash or backslash.
     *
     * @param  string $basePath Absolute path to the project root.
     *
     * @return void
     */
    private function setBasePath(string $basePath): void
    {
        $this->basePath = rtrim($basePath, '/\\');
    }

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
        return $this['router']->get($pattern, $callable);
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
        return $this['router']->post($pattern, $callable);
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
        return $this['router']->put($pattern, $callable);
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
        return $this['router']->patch($pattern, $callable);
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
        return $this['router']->delete($pattern, $callable);
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
        return $this['router']->options($pattern, $callable);
    }

    /**
     * Registers a route that responds to all HTTP methods.
     *
     * @param  string $pattern
     * @param  mixed  $callable
     *
     * @return Route
     */
    public function any(string $pattern, mixed $callable): Route
    {
        return $this['router']->any($pattern, $callable);
    }

    /**
     * Registers a route that responds to multiple HTTP methods.
     *
     * ```php
     * $app->map(['GET', 'POST'], '/form', $callback);
     * ```
     *
     * @param  string[] $methods
     * @param  string   $pattern
     * @param  mixed    $callable
     *
     * @return Route
     */
    public function map(array $methods, string $pattern, mixed $callable): Route
    {
        return $this['router']->map($methods, $pattern, $callable);
    }

    /**
     * Groups routes under a shared URL prefix and optional middleware.
     *
     * ```php
     * $app->group('/admin', function ($app) {
     *     $app->get('/dashboard', $callback);
     *     $app->get('/users', $callback);
     * }, ['auth']);
     * ```
     *
     * @param  string   $prefix
     * @param  callable $callback
     * @param  string[] $middleware
     *
     * @return RouteGroup
     */
    public function group(string $prefix, callable $callback, array $middleware = []): RouteGroup
    {
        return $this['router']->group($prefix, $callback, $middleware);
    }

    /**
     * Generates a URL for a named route, substituting route parameters.
     *
     * ```php
     * $app->get('/users/{id}', $callback)->name('users.show');
     * $app->urlFor('users.show', ['id' => 42]); // /users/42
     * ```
     *
     * @param  string               $name
     * @param  array<string, mixed> $params
     *
     * @return string
     */
    public function urlFor(string $name, array $params = []): string
    {
        return $this['router']->urlFor($name, $params);
    }

    /**
     * Renders a view template and returns an HTML Response.
     *
     * @param  string               $template
     * @param  array<string, mixed> $data
     *
     * @return Response
     */
    public function view(string $template, array $data = []): Response
    {
        $content = $this['view']->render($template, $data);

        return (new Response())
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->body($content);
    }

    /**
     * Reads a value from the application config using dot notation.
     *
     * ```php
     * $app->config('mail.smtp.host', 'localhost');
     * ```
     *
     * @param  string $key
     * @param  mixed  $default
     *
     * @return mixed
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return $this['config']->get($key, $default);
    }

    /**
     * Creates and returns a Validator instance for the given data and rules.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @param  array<string, string> $messages
     *
     * @return Validator
     */
    public function validator(array $data, array $rules, array $messages = []): Validator
    {
        return ($this['validator'])($data, $rules, $messages);
    }

    /**
     * Joins a base path and one or more segments using the OS directory
     * separator, stripping duplicate slashes at each boundary.
     *
     * ```php
     * $app->joinPaths('/var/www', 'public', 'css'); // /var/www/public/css
     * ```
     *
     * @param  string $base
     * @param  string ...$segments
     *
     * @return string
     */
    public function joinPaths(string $base, string ...$segments): string
    {
        foreach ($segments as $segment) {
            $base = rtrim($base, '/\\').DIRECTORY_SEPARATOR.ltrim($segment, '/\\');
        }

        return $base;
    }

    /**
     * Returns the absolute path to the project root, optionally appending
     * segments.
     *
     * @param  string ...$segments
     *
     * @return string
     */
    public function basePath(string ...$segments): string
    {
        return $this->joinPaths($this->basePath, ...$segments);
    }

    /**
     * Returns the absolute path to the public directory.
     *
     * @param  string ...$segments
     *
     * @return string
     */
    public function publicPath(string ...$segments): string
    {
        return $this->joinPaths($this->basePath('public'), ...$segments);
    }

    /**
     * Returns the absolute path to the resources directory.
     *
     * @param  string ...$segments
     *
     * @return string
     */
    public function resourcesPath(string ...$segments): string
    {
        return $this->joinPaths($this->basePath('resources'), ...$segments);
    }

    /**
     * Returns the absolute path to the storage directory.
     *
     * @param  string ...$segments
     *
     * @return string
     */
    public function storagePath(string ...$segments): string
    {
        return $this->joinPaths($this->basePath('storage'), ...$segments);
    }

    /**
     * Returns the absolute path to the logs directory.
     *
     * @param  string ...$segments
     *
     * @return string
     */
    public function logsPath(string ...$segments): string
    {
        return $this->joinPaths($this->storagePath('logs'), ...$segments);
    }

    /**
     * Returns the absolute path to the cache directory.
     *
     * @param  string ...$segments
     *
     * @return string
     */
    public function cachePath(string ...$segments): string
    {
        return $this->joinPaths($this->storagePath('cache'), ...$segments);
    }

    /**
     * Returns the absolute path to the database directory.
     *
     * @param  string ...$segments
     *
     * @return string
     */
    public function databasePath(string ...$segments): string
    {
        return $this->joinPaths($this->basePath('database'), ...$segments);
    }

    /**
     * Returns the absolute path to the views directory.
     *
     * @param  string ...$segments
     *
     * @return string
     */
    public function viewsPath(string ...$segments): string
    {
        return $this->joinPaths($this->resourcesPath('views'), ...$segments);
    }

    /**
     * Registers a single service provider by calling its register() method.
     *
     * @param  ServiceProviderInterface $provider
     *
     * @return static
     */
    public function registerProvider(ServiceProviderInterface $provider): static
    {
        $provider->register($this);

        $this->providers[] = $provider;

        return $this;
    }

    /**
     * Registers multiple service providers in order.
     *
     * @param  ServiceProviderInterface[] $providers
     *
     * @return static
     */
    public function registerProviders(array $providers): static
    {
        foreach ($providers as $provider) {
            $this->registerProvider($provider);
        }

        return $this;
    }

    /**
     * Calls boot() on every BootableProviderInterface provider. Idempotent —
     * safe to call multiple times.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->providers as $provider) {
            if ($provider instanceof BootableProviderInterface) {
                $provider->boot($this);
            }
        }

        $this->booted = true;
    }

    /**
     * Returns true when the application is running from the command line.
     *
     * @return bool
     */
    public function isCli(): bool
    {
        return PHP_SAPI === 'cli';
    }

    /**
     * Silently no-ops on CLI so the same bootstrap file works for both web and
     * console entry points.
     *
     * @return void
     */
    public function run(): void
    {
        if ($this->isCli()) {
            return;
        }

        $this->boot();

        $kernel   = new Kernel($this);
        $request  = Request::createFromGlobals();
        $response = $kernel->handle($request);

        $response->send();

        $kernel->terminate($request, $response);
    }

    /**
     * Promotes all PHP errors to ErrorException so every error is uniformly
     * catchable.
     *
     * @return void
     */
    private function registerErrorReporting(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (error_reporting() & $severity) {
                throw new ErrorException($message, 0, $severity, $file, $line);
            }

            return false;
        });
    }

    /**
     * Registers a handler for a specific HTTP status code. The handler receives
     * (Request $request, Response $response, Throwable $e) and must return a
     * Response.
     *
     * @param  int      $status
     * @param  callable $handler
     *
     * @return void
     */
    public function registerErrorHandler(int $status, callable $handler): void
    {
        $this->errorHandlers[$status] = $handler;
    }

    /**
     * Returns the registered handler for the given HTTP status code, or null
     * if none.
     *
     * @param  int $status
     *
     * @return callable|null
     */
    public function getErrorHandler(int $status): ?callable
    {
        return $this->errorHandlers[$status] ?? null;
    }

    /**
     * Registers middleware that runs on every request before route dispatch.
     *
     * @param  string|MiddlewareInterface ...$middleware
     *
     * @return static
     */
    public function registerMiddleware(string|MiddlewareInterface ...$middleware): static
    {
        array_push($this->middleware, ...$middleware);

        return $this;
    }

    /**
     * Binds a short alias to a middleware factory. The factory receives the
     * application and an array of parameters parsed from the
     * "alias:param1,param2" route syntax.
     *
     * ```php
     * $app->registerMiddlewareAlias('throttle', function ($app, $params) {
     *     return new RateLimitMiddleware($app['cache'], (int) $params[0], (int) $params[1]);
     * });
     * ```
     *
     * @param  string         $alias
     * @param  string|Closure $factory
     *
     * @return static
     */
    public function registerMiddlewareAlias(string $alias, string|Closure $factory): static
    {
        $this->middlewareAliases[$alias] = $factory;

        return $this;
    }

    /**
     * Bundles a set of middleware aliases under a single group name for use in
     * route definitions.
     *
     * ```php
     * $app->registerMiddlewareGroup('web', ['csrf', 'auth']);
     * ```
     *
     * @param  string   $name
     * @param  string[] $middleware
     *
     * @return static
     */
    public function registerMiddlewareGroup(string $name, array $middleware): static
    {
        $this->middlewareGroups[$name] = $middleware;

        return $this;
    }

    /**
     * Returns the global middleware stack.
     *
     * @return array<string|MiddlewareInterface>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Resolves a mixed list of alias strings and MiddlewareInterface instances,
     * expanding groups recursively.
     *
     * @param  array<string|MiddlewareInterface> $list
     *
     * @return MiddlewareInterface[]
     */
    public function resolveMiddlewareList(array $list): array
    {
        $resolved = [];

        foreach ($list as $item) {
            if ($item instanceof MiddlewareInterface) {
                $resolved[] = $item;
                continue;
            }

            $alias = explode(':', $item, 2)[0];

            if (isset($this->middlewareGroups[$alias])) {
                array_push($resolved, ...$this->resolveMiddlewareList($this->middlewareGroups[$alias]));
                continue;
            }

            $resolved[] = $this->resolveMiddlewareAlias($item);
        }

        return $resolved;
    }

    /**
     * Parses the "alias:param1,param2" colon syntax and invokes the registered
     * factory.
     *
     * ```php
     * $this->resolveMiddlewareAlias('throttle:60,1');
     * ```
     *
     * @param  string $middleware
     *
     * @throws InvalidArgumentException If the alias has not been registered.
     *
     * @return MiddlewareInterface
     */
    private function resolveMiddlewareAlias(string $middleware): MiddlewareInterface
    {
        $parts   = explode(':', $middleware, 2);
        $alias   = $parts[0];
        $params  = isset($parts[1]) ? explode(',', $parts[1]) : [];

        if (!isset($this->middlewareAliases[$alias])) {
            throw new InvalidArgumentException(
                sprintf('Unknown middleware alias "%s".', $alias)
            );
        }

        $factory = $this->middlewareAliases[$alias];

        if ($factory instanceof Closure) {
            return $factory($this, $params);
        }

        return new $factory(...$params);
    }
}
