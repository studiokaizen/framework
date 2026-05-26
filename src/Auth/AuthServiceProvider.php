<?php

declare(strict_types=1);

namespace Zen\Auth;

use Zen\Application;
use Zen\DependencyInjection\ServiceProviderInterface;

/**
 * Registers the auth and tokens services in the application container.
 */
class AuthServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds AuthManager as 'auth' and TokenManager as 'tokens'.
     *
     * @param  Application $app
     *
     * @return void
     */
    public function register(Application $app): void
    {
        $app['auth'] = static function ($app) {
            return new AuthManager(
                db:            $app['db'],
                hasher:        $app['hasher'],
                session:       $app['session'],
                table:         $app->config('auth.table', 'users'),
                usernameField: $app->config('auth.username_field', 'email'),
                passwordField: $app->config('auth.password_field', 'password'),
            );
        };

        $app['tokens'] = static function ($app) {
            return new TokenManager($app['db']);
        };
    }
}
