<?php

declare(strict_types=1);

namespace Zen\Scheduling;

use Zen\Application;
use Zen\DependencyInjection\ServiceProviderInterface;

/**
 * Registers the Scheduler service in the application container.
 */
class SchedulingServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds the 'scheduler' service, injecting the application instance.
     *
     * @param  Application $app
     *
     * @return void
     */
    public function register(Application $app): void
    {
        $app['scheduler'] = static function (Application $app): Scheduler {
            return new Scheduler($app);
        };
    }
}
