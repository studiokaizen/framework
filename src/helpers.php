<?php

declare(strict_types=1);

if (!function_exists('app')) {
    /**
     * Resolve a service from the application container.
     *
     * Usage in jobs, listeners, or anywhere the container is not injected:
     *   app('mailer'), app('logger'), app('db'), etc.
     */
    function app(?string $key = null): mixed
    {
        $instance = $GLOBALS['app'] ?? null;

        if ($key === null || $instance === null) {
            return $instance;
        }

        return $instance[$key];
    }
}
