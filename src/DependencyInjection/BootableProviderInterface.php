<?php

declare(strict_types=1);

namespace Zen\DependencyInjection;

use Zen\Application;

/**
 * Implemented by service providers that need to run initialisation logic
 * after all providers have been registered.
 */
interface BootableProviderInterface
{
    /**
     * Runs boot logic after all service providers have been registered.
     *
     * @param  Application $app
     *
     * @return void
     */
    public function boot(Application $app): void;
}
