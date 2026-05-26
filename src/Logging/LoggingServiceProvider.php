<?php

declare(strict_types=1);

namespace Zen\Logging;

use Zen\Application;
use Zen\DependencyInjection\ServiceProviderInterface;

/**
 * Registers the Logger service in the application container.
 */
class LoggingServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds the 'logger' service, resolving the log file path from config.
     *
     * @param  Application $app
     *
     * @return void
     */
    public function register(Application $app): void
    {
        $app['logger'] = function (Application $app): Logger {
            $file = $app->config('logging.file', 'app.log');

            return new Logger($app->logsPath($file));
        };
    }
}
