<?php

declare(strict_types=1);

namespace Zen\Console\Commands;

use ReflectionClass;
use Zen\Console\Command;
use Zen\Routing\Router;

/**
 * Prints a formatted, colour-coded table of every registered route.
 */
class RouteListCommand extends Command
{
    /**
     * ANSI colour codes keyed by HTTP method name.
     *
     * @var array<string, string>
     */
    private const METHOD_COLORS = [
        'GET'     => "\033[32m",
        'POST'    => "\033[33m",
        'PUT'     => "\033[34m",
        'PATCH'   => "\033[36m",
        'DELETE'  => "\033[31m",
        'OPTIONS' => "\033[37m",
    ];

    /**
     * Injects the router instance used to retrieve route definitions.
     *
     * @param  Router $router
     *
     * @return void
     */
    public function __construct(private readonly Router $router)
    {
    }

    /**
     * Returns the command name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'route:list';
    }

    /**
     * Returns a short description of what the command does.
     *
     * @return string
     */
    public function description(): string
    {
        return 'List all registered routes.';
    }

    /**
     * Queries the router for all routes and prints them in a padded table with
     * colour-highlighted HTTP methods.
     *
     * @param  string[] $args
     * @param  mixed[]  $options
     *
     * @return int Exit code 0 on success.
     */
    public function handle(array $args, array $options): int
    {
        $routes = $this->router->getRoutes();

        if ($routes === []) {
            $this->warn('No routes registered.');

            return 0;
        }

        $rows = array_map(function (mixed $route): array {
            $methods    = implode('|', $route->getMethods());
            $middleware = array_map(
                static fn(mixed $mw): string => (new ReflectionClass($mw))->getShortName(),
                $route->getMiddleware(),
            );

            return [
                'method'     => $methods,
                'uri'        => $route->getPattern(),
                'name'       => $route->getName() ?: '-',
                'middleware' => $middleware ? implode(', ', $middleware) : '-',
            ];
        }, $routes);

        $methodWidth = max(6, ...array_map(static fn(array $r): int => strlen($r['method']), $rows));
        $uriWidth    = max(3, ...array_map(static fn(array $r): int => strlen($r['uri']), $rows));
        $nameWidth   = max(4, ...array_map(static fn(array $r): int => strlen($r['name']), $rows));

        $this->line('');

        printf(
            "  %-{$methodWidth}s  %-{$uriWidth}s  %-{$nameWidth}s  %s\n",
            'METHOD',
            'URI',
            'NAME',
            'MIDDLEWARE',
        );

        $this->line('  ' . str_repeat('─', $methodWidth + $uriWidth + $nameWidth + 26));

        foreach ($rows as $row) {
            $colored = $this->colorMethod($row['method']);
            $pad     = str_repeat(' ', $methodWidth - strlen($row['method']));

            printf(
                "  %s%s  %-{$uriWidth}s  %-{$nameWidth}s  %s\n",
                $colored,
                $pad,
                $row['uri'],
                $row['name'],
                $row['middleware'],
            );
        }

        $this->line('');
        printf("  %d route(s) registered.\n\n", count($routes));

        return 0;
    }

    /**
     * Wraps each pipe-separated HTTP method name with its ANSI colour code.
     *
     * @param  string $method One or more methods joined by '|', e.g. 'GET|POST'.
     *
     * @return string ANSI-coloured string.
     */
    private function colorMethod(string $method): string
    {
        $parts = array_map(function (string $m): string {
            $color = self::METHOD_COLORS[$m] ?? "\033[37m";

            return $color . $m . "\033[0m";
        }, explode('|', $method));

        return implode('|', $parts);
    }
}
