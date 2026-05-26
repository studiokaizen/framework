<?php

declare(strict_types=1);

namespace Zen\Events;

/**
 * Base class for all framework and application events, providing propagation
 * control so listeners can prevent subsequent listeners from being called.
 */
class Event
{
    /**
     * Whether propagation has been stopped for this event.
     *
     * @var bool
     */
    private bool $propagationStopped = false;

    /**
     * Stops the event from being passed to further listeners.
     *
     * @return void
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * Returns true when propagation has been stopped.
     *
     * @return bool
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
