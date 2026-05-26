<?php

declare(strict_types=1);

namespace Zen\Storage;

use Zen\Application;
use Zen\DependencyInjection\ServiceProviderInterface;

/**
 * Registers the StorageManager service in the application container.
 */
class StorageServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds the 'storage' service, merging default disk definitions with any
     * application-level overrides from config('storage').
     *
     * @param  Application $app
     *
     * @return void
     */
    public function register(Application $app): void
    {
        $app['storage'] = static function (Application $app): StorageManager {
            $storage = $app->config('storage', []);

            $storage['disks']           ??= [];
            $storage['disks']['local']  ??= ['root' => $app->storagePath('app')];
            $storage['disks']['public'] ??= ['root' => $app->publicPath('uploads'), 'url' => '/uploads'];
            $storage['default']         ??= 'local';

            return new StorageManager($storage);
        };
    }
}
