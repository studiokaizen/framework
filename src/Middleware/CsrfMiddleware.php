<?php

declare(strict_types=1);

namespace Zen\Middleware;

use Zen\Http\HttpException;
use Zen\Http\Request;
use Zen\Http\Response;
use Zen\Session\Session;
use Zen\View\Engine;

/**
 * Validates the CSRF token on state-changing requests and shares the token
 * and a hidden-input helper with every view.
 */
class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * Session key used to store the CSRF token.
     *
     * @var string
     */
    private const SESSION_KEY = '_csrf_token';

    /**
     * HTTP methods that do not require CSRF protection.
     *
     * @var array<int, string>
     */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * Creates the middleware with the session and view engine.
     *
     * @param  Session $session
     * @param  Engine  $engine
     *
     * @return void
     */
    public function __construct(
        private readonly Session $session,
        private readonly Engine  $engine,
    )
    {
    }

    /**
     * Shares the CSRF token with views and validates it for state-changing
     * requests, throwing a 419 exception when the token is invalid.
     *
     * @param  Request  $request
     * @param  Response $response
     * @param  callable $next
     *
     * @throws HttpException With status 419 when the CSRF token does not match.
     *
     * @return Response
     */
    public function process(Request $request, Response $response, callable $next): Response
    {
        $this->session->start();
        $token = $this->token();

        $this->engine->share('csrf_token', $token);
        $this->engine->share('csrf_field', sprintf(
            '<input type="hidden" name="_token" value="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8'),
        ));

        if (!in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            $submitted = $request->input('_token')
                ?? $request->getHeader('X-CSRF-Token');

            if (!hash_equals($token, (string) $submitted)) {
                throw new HttpException(419, 'CSRF token mismatch.');
            }
        }

        return $next($request, $response);
    }

    /**
     * Returns the current CSRF token, generating and storing one if absent.
     *
     * @return string
     */
    private function token(): string
    {
        if (!$this->session->has(self::SESSION_KEY)) {
            $this->session->set(self::SESSION_KEY, bin2hex(random_bytes(32)));
        }

        return $this->session->get(self::SESSION_KEY);
    }
}
