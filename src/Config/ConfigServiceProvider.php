<?php

declare(strict_types=1);

namespace Zen\Config;

use Zen\Application;
use Zen\DependencyInjection\ServiceProviderInterface;

/**
 * Registers the Config instance in the service container under the 'config'
 * key, loading values from the root config.php file.
 */
class ConfigServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds a Config factory that loads config.php into the container.
     *
     * @param  Application $app
     *
     * @return void
     */
    public function register(Application $app): void
    {
        $app['config'] = function (Application $app): Config {
            $path  = $app->basePath('config.php');
            $items = file_exists($path) ? require $path : [];

            return new Config($items);
        };
    }
}
