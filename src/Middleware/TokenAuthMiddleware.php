<?php

declare(strict_types=1);

namespace Zen\Middleware;

use Zen\Auth\AuthManager;
use Zen\Auth\TokenManager;
use Zen\Http\Request;
use Zen\Http\Response;

/**
 * Authenticates API requests by validating a Bearer token from the
 * Authorization header and loading the associated user into the auth manager.
 */
class TokenAuthMiddleware implements MiddlewareInterface
{
    /**
     * Creates the middleware with the token and auth managers.
     *
     * @param  TokenManager $tokens
     * @param  AuthManager  $auth
     *
     * @return void
     */
    public function __construct(
        private readonly TokenManager $tokens,
        private readonly AuthManager  $auth,
    )
    {
    }

    /**
     * Validates the Bearer token from the Authorization header and, if valid,
     * loads the owning user before passing to the next layer. Returns a 401
     * JSON response when authentication fails.
     *
     * @param  Request  $request
     * @param  Response $response
     * @param  callable $next
     *
     * @return Response
     */
    public function process(Request $request, Response $response, callable $next): Response
    {
        $header = $request->getHeader('Authorization');

        if (!str_starts_with($header, 'Bearer ')) {
            return $response->json(['message' => 'Unauthenticated.'], 401);
        }

        $plainToken = substr($header, 7);
        $token      = $this->tokens->find($plainToken);

        if ($token === null) {
            return $response->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = $this->auth->findById((int) $token->tokenable_id);

        if ($user === null) {
            return $response->json(['message' => 'Unauthenticated.'], 401);
        }

        $this->tokens->touch($token->id);
        $this->auth->loginViaToken($user, $token);

        return $next($request, $response);
    }
}
