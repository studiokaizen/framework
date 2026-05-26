<?php

declare(strict_types=1);

namespace Zen\Events;

/**
 * Manages event listeners and dispatches events to all registered listeners,
 * respecting propagation stops on Event instances.
 */
class EventDispatcher
{
    /**
     * Registered listeners grouped by event class name.
     *
     * @var array<string, array<int, callable>>
     */
    private array $listeners = [];

    /**
     * Registers a callable listener for the given event class name.
     *
     * @param  string   $event
     * @param  callable $listener
     *
     * @return void
     */
    public function addListener(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    /**
     * Registers all listeners from an EventSubscriberInterface implementation.
     *
     * @param  EventSubscriberInterface $subscriber
     *
     * @return void
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        foreach ($subscriber::getSubscribedEvents() as $event => $method) {
            $this->addListener($event, [$subscriber, $method]);
        }
    }

    /**
     * Dispatches the given event to all matching listeners and returns it.
     * Stops dispatching when propagation is halted on an Event subclass.
     *
     * @param  object $event
     *
     * @return object
     */
    public function dispatch(object $event): object
    {
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            if ($event instanceof Event && $event->isPropagationStopped()) {
                break;
            }

            $listener($event);
        }

        return $event;
    }
}
