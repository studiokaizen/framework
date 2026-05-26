<?php

declare(strict_types=1);

namespace Zen\Middleware;

use Zen\Auth\AuthManager;
use Zen\Http\Request;
use Zen\Http\Response;

/**
 * Redirects authenticated users away from guest-only routes such as login
 * and registration pages.
 */
class GuestMiddleware implements MiddlewareInterface
{
    /**
     * Creates the middleware with an auth manager and optional redirect path.
     *
     * @param  AuthManager $auth
     * @param  string      $redirectTo  Path to redirect authenticated users to.
     *
     * @return void
     */
    public function __construct(
        private readonly AuthManager $auth,
        private readonly string      $redirectTo = '/',
    )
    {
    }

    /**
     * Redirects authenticated users to the home path, otherwise passes the
     * request to the next layer.
     *
     * @param  Request  $request
     * @param  Response $response
     * @param  callable $next
     *
     * @return Response
     */
    public function process(Request $request, Response $response, callable $next): Response
    {
        if ($this->auth->check()) {
            return $response->status(302)->header('Location', $this->redirectTo);
        }

        return $next($request, $response);
    }
}
