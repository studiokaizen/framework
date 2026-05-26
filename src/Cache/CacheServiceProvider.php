<?php

declare(strict_types=1);

namespace Zen\Cache;

use Zen\Application;
use Zen\DependencyInjection\ServiceProviderInterface;

/**
 * Registers the file-based Cache instance in the service container under the
 * 'cache' key.
 */
class CacheServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds a Cache factory that reads the cache path and TTL from config.
     *
     * @param  Application $app
     *
     * @return void
     */
    public function register(Application $app): void
    {
        $app['cache'] = function (Application $app): Cache {
            return new Cache(
                $app->cachePath(),
                $app->config('cache.ttl', 3600),
            );
        };
    }
}
