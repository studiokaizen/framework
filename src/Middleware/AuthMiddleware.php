<?php

declare(strict_types=1);

namespace Zen\Middleware;

use Zen\Auth\AuthManager;
use Zen\Http\Request;
use Zen\Http\Response;

/**
 * Redirects unauthenticated users to the login page, protecting routes that
 * require a logged-in session.
 */
class AuthMiddleware implements MiddlewareInterface
{
    /**
     * Creates the middleware with an auth manager and optional redirect path.
     *
     * @param  AuthManager $auth
     * @param  string      $redirectTo  Path to redirect guests to.
     *
     * @return void
     */
    public function __construct(
        private readonly AuthManager $auth,
        private readonly string      $redirectTo = '/login',
    )
    {
    }

    /**
     * Redirects to the login page when the user is not authenticated,
     * otherwise passes the request to the next layer.
     *
     * @param  Request  $request
     * @param  Response $response
     * @param  callable $next
     *
     * @return Response
     */
    public function process(Request $request, Response $response, callable $next): Response
    {
        if ($this->auth->guest()) {
            return $response->status(302)->header('Location', $this->redirectTo);
        }

        return $next($request, $response);
    }
}
