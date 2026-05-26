<?php

declare(strict_types=1);

namespace Zen\Routing;

use Zen\Application;
use Zen\DependencyInjection\ServiceProviderInterface;

/**
 * Registers the Router instance in the service container under the 'router'
 * key.
 */
class RoutingServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds a fresh Router factory to the container.
     *
     * @param  Application $app
     *
     * @return void
     */
    public function register(Application $app): void
    {
        $app['router'] = static function () {
            return new Router();
        };
    }
}
