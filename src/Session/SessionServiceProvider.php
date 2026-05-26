<?php

declare(strict_types=1);

namespace Zen\Session;

use Zen\Application;
use Zen\DependencyInjection\BootableProviderInterface;
use Zen\DependencyInjection\ServiceProviderInterface;

/**
 * Registers and boots the Session service in the application container.
 */
class SessionServiceProvider implements ServiceProviderInterface, BootableProviderInterface
{
    /**
     * Binds the 'session' service using configuration from config('session').
     *
     * @param  Application $app
     *
     * @return void
     */
    public function register(Application $app): void
    {
        $app['session'] = static function (Application $app): Session {
            return new Session($app->config('session', []));
        };
    }

    /**
     * Starts the session immediately after all providers have been registered.
     *
     * @param  Application $app
     *
     * @return void
     */
    public function boot(Application $app): void
    {
        $app['session']->start();
    }
}
