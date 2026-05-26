<?php

declare(strict_types=1);

namespace Zen\Middleware;

use Zen\Http\Request;
use Zen\Http\Response;

/**
 * Handles Cross-Origin Resource Sharing (CORS) by adding the appropriate
 * access-control headers and responding to preflight OPTIONS requests.
 */
class CorsMiddleware implements MiddlewareInterface
{
    /**
     * Default CORS configuration values.
     *
     * @var array<string, mixed>
     */
    private const DEFAULTS = [
        'allowed_origins'  => ['*'],
        'allowed_methods'  => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers'  => ['Content-Type', 'Authorization', 'X-CSRF-Token', 'X-Requested-With'],
        'exposed_headers'  => [],
        'max_age'          => 0,
        'allow_credentials' => false,
    ];

    /**
     * Merged CORS configuration for this middleware instance.
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Creates the middleware with optional CORS overrides.
     *
     * @param  array<string, mixed> $config
     *
     * @return void
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge(self::DEFAULTS, $config);
    }

    /**
     * Adds CORS headers to the response or handles preflight requests.
     * Requests without an Origin header are passed through unchanged.
     *
     * @param  Request  $request
     * @param  Response $response
     * @param  callable $next
     *
     * @return Response
     */
    public function process(Request $request, Response $response, callable $next): Response
    {
        $origin = $request->getHeader('Origin');

        if ($origin === '') {
            return $request->getMethod() === 'OPTIONS'
                ? (new Response())->status(204)
                : $next($request, $response);
        }

        $allowed = $this->resolveOrigin($origin);

        if ($request->getMethod() === 'OPTIONS') {
            return $this->preflight(new Response(), $allowed);
        }

        $response = $next($request, $response);

        return $this->addHeaders($response, $allowed);
    }

    /**
     * Determines the allowed origin value to include in the response header.
     *
     * @param  string $origin
     *
     * @return string
     */
    private function resolveOrigin(string $origin): string
    {
        $allowed = $this->config['allowed_origins'];

        if (in_array('*', $allowed, true)) {
            return $this->config['allow_credentials'] ? $origin : '*';
        }

        return in_array($origin, $allowed, true) ? $origin : '';
    }

    /**
     * Builds a 204 preflight response with all CORS headers set.
     *
     * @param  Response $response
     * @param  string   $origin
     *
     * @return Response
     */
    private function preflight(Response $response, string $origin): Response
    {
        if ($origin === '') {
            return $response->status(204);
        }

        return $response
            ->status(204)
            ->header('Access-Control-Allow-Origin', $origin)
            ->header('Access-Control-Allow-Methods', implode(', ', $this->config['allowed_methods']))
            ->header('Access-Control-Allow-Headers', implode(', ', $this->config['allowed_headers']))
            ->header('Access-Control-Max-Age', (string) $this->config['max_age'])
            ->header('Vary', 'Origin');
    }

    /**
     * Adds CORS response headers to the given response.
     *
     * @param  Response $response
     * @param  string   $origin
     *
     * @return Response
     */
    private function addHeaders(Response $response, string $origin): Response
    {
        if ($origin === '') {
            return $response;
        }

        $response = $response
            ->header('Access-Control-Allow-Origin', $origin)
            ->header('Vary', 'Origin');

        if ($this->config['allow_credentials']) {
            $response = $response->header('Access-Control-Allow-Credentials', 'true');
        }

        if ($this->config['exposed_headers'] !== []) {
            $response = $response->header(
                'Access-Control-Expose-Headers',
                implode(', ', $this->config['exposed_headers']),
            );
        }

        return $response;
    }
}
