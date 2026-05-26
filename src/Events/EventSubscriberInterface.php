<?php

declare(strict_types=1);

namespace Zen\Events;

/**
 * Implemented by classes that subscribe to multiple events at once by
 * returning a map of event class names to listener method names.
 */
interface EventSubscriberInterface
{
    /**
     * Returns a map of event class names to the method that handles each one.
     *
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array;
}
