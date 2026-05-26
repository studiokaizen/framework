<?php

declare(strict_types=1);

namespace Zen\Queue;

use Zen\Application;
use Zen\DependencyInjection\ServiceProviderInterface;

/**
 * Registers the Queue and Worker services in the application container.
 */
class QueueServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds the 'queue' and 'worker' services.
     *
     * @param  Application $app
     *
     * @return void
     */
    public function register(Application $app): void
    {
        $app['queue'] = static function ($app) {
            return new Queue($app['db']);
        };

        $app['worker'] = static function ($app) {
            return new Worker($app['queue']);
        };
    }
}
