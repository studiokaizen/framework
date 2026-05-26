<?php

declare(strict_types=1);

namespace Zen\Hashing;

use Zen\Application;
use Zen\DependencyInjection\ServiceProviderInterface;

/**
 * Registers the Hasher service in the application container.
 */
class HashingServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds the 'hasher' service using driver and rounds from configuration.
     *
     * @param  Application $app
     *
     * @return void
     */
    public function register(Application $app): void
    {
        $app['hasher'] = function (Application $app): Hasher {
            return new Hasher(
                $app->config('hashing.driver', 'bcrypt'),
                $app->config('hashing.rounds', 12),
            );
        };
    }
}
