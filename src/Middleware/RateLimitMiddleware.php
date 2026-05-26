<?php

declare(strict_types=1);

namespace Zen\Middleware;

use Zen\Cache\Cache;
use Zen\Http\Request;
use Zen\Http\Response;

/**
 * Enforces a sliding-window rate limit per client, returning 429 responses
 * with standard RateLimit headers when the limit is exceeded.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * Creates the middleware with cache, limit, window, and optional key
     * resolver.
     *
     * @param  Cache         $cache
     * @param  int           $maxRequests    Maximum allowed requests per window.
     * @param  int           $windowSeconds  Window duration in seconds.
     * @param  callable|null $keyResolver    Resolves a cache key from the request;
     *                                       defaults to the client IP address.
     *
     * @return void
     */
    public function __construct(
        private readonly Cache         $cache,
        private readonly int           $maxRequests    = 60,
        private readonly int           $windowSeconds  = 60,
        private readonly ?callable     $keyResolver    = null,
    )
    {
    }

    /**
     * Increments the hit counter for the current window, attaches RateLimit
     * headers, and returns a 429 response when the limit is exceeded.
     *
     * @param  Request  $request
     * @param  Response $response
     * @param  callable $next
     *
     * @return Response
     */
    public function process(Request $request, Response $response, callable $next): Response
    {
        $base     = 'rl:' . $this->resolveKey($request);
        $hitsKey  = $base . ':hits';
        $resetKey = $base . ':reset';

        $now   = time();
        $reset = (int) $this->cache->get($resetKey, 0);

        if ($reset === 0 || $now >= $reset) {
            $reset = $now + $this->windowSeconds;
            $ttl   = $this->windowSeconds;
            $hits  = 1;
        } else {
            $ttl  = $reset - $now;
            $hits = (int) $this->cache->get($hitsKey, 0) + 1;
        }

        $this->cache->set($resetKey, $reset, $ttl + 1);
        $this->cache->set($hitsKey, $hits, $ttl + 1);

        $remaining = max(0, $this->maxRequests - $hits);

        if ($hits > $this->maxRequests) {
            return $response
                ->status(429)
                ->header('X-RateLimit-Limit', (string) $this->maxRequests)
                ->header('X-RateLimit-Remaining', '0')
                ->header('X-RateLimit-Reset', (string) $reset)
                ->header('Retry-After', (string) ($reset - $now))
                ->body('Too Many Requests');
        }

        $response = $next($request, $response);

        return $response
            ->header('X-RateLimit-Limit', (string) $this->maxRequests)
            ->header('X-RateLimit-Remaining', (string) $remaining)
            ->header('X-RateLimit-Reset', (string) $reset);
    }

    /**
     * Resolves the cache key for this request using the custom resolver or
     * falls back to the client IP address.
     *
     * @param  Request $request
     *
     * @return string
     */
    private function resolveKey(Request $request): string
    {
        if ($this->keyResolver !== null) {
            return (string) ($this->keyResolver)($request);
        }

        return $request->getIp();
    }
}
