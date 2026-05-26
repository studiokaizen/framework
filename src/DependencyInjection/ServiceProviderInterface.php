<?php

declare(strict_types=1);

namespace Zen\DependencyInjection;

use Zen\Application;

/**
 * Implemented by classes that bind services into the application container
 * during the registration phase.
 */
interface ServiceProviderInterface
{
    /**
     * Binds services and values into the container.
     *
     * @param  Application $app
     *
     * @return void
     */
    public function register(Application $app): void;
}
