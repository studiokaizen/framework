<?php

declare(strict_types=1);

namespace Zen\View;

use Zen\Application;
use Zen\DependencyInjection\ServiceProviderInterface;

/**
 * Registers the view Engine service in the application container.
 */
class ViewServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds the 'view' service using the application views path and optional
     * file extension from config('views.extension').
     *
     * @param  Application $app
     *
     * @return void
     */
    public function register(Application $app): void
    {
        $app['view'] = function (Application $app): Engine {
            $path = $app->viewsPath();

            return new Engine($path, $app->config('views.extension', 'php'));
        };
    }
}
