<?php

declare(strict_types=1);

namespace Zen\Encryption;

use Zen\Application;
use Zen\DependencyInjection\ServiceProviderInterface;

/**
 * Registers the Encrypter service in the application container.
 */
class EncryptionServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds the 'encrypter' service using the key from config('app.key').
     *
     * @param  Application $app
     *
     * @return void
     */
    public function register(Application $app): void
    {
        $app['encrypter'] = function (Application $app): Encrypter {
            $key = $app->config('app.key', '');

            return new Encrypter($key);
        };
    }
}
