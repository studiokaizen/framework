<?php

declare(strict_types=1);

namespace Zen\Events;

use Zen\Application;
use Zen\DependencyInjection\ServiceProviderInterface;

/**
 * Registers the EventDispatcher in the service container under the 'events'
 * key.
 */
class EventServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds a fresh EventDispatcher factory to the container.
     *
     * @param  Application $app
     *
     * @return void
     */
    public function register(Application $app): void
    {
        $app['events'] = static function () {
            return new EventDispatcher();
        };
    }
}
