<?php

declare(strict_types=1);

namespace Zen\Scheduling;

use DateTimeImmutable;
use DateTimeInterface;
use Zen\Application;

/**
 * Registers scheduled events and dispatches the ones that are due at the
 * current time.
 */
class Scheduler
{
    /**
     * All registered scheduled events.
     *
     * @var Event[]
     */
    private array $events = [];

    /**
     * Stores the application instance used to run console commands.
     *
     * @param  Application $app
     *
     * @return void
     */
    public function __construct(private readonly Application $app)
    {
    }

    /**
     * Registers a callback as a scheduled event and returns the Event for
     * further configuration.
     *
     * @param  callable $callback
     *
     * @return Event
     */
    public function call(callable $callback): Event
    {
        $event          = new Event($callback);
        $this->events[] = $event;

        return $event;
    }

    /**
     * Registers a console command string as a scheduled event, parsing
     * arguments and options from the command string at run time.
     *
     * @param  string $command Full command string, e.g. 'cache:clear'.
     *
     * @return Event
     */
    public function command(string $command): Event
    {
        return $this->call(function () use ($command): void {
            $argv = array_merge(['zen'], $this->parseCommand($command));
            $this->app['console']->run($argv);
        });
    }

    /**
     * Returns all events whose cron expression matches the given time.
     *
     * @param  DateTimeInterface|null $now Defaults to the current time.
     *
     * @return Event[]
     */
    public function dueEvents(?DateTimeInterface $now = null): array
    {
        $now ??= new DateTimeImmutable();

        return array_values(array_filter($this->events, fn(Event $e) => $e->isDue($now)));
    }

    /**
     * Runs all due events and returns their descriptions.
     *
     * @param  DateTimeInterface|null $now Defaults to the current time.
     *
     * @return string[] Descriptions of the events that were run.
     */
    public function run(?DateTimeInterface $now = null): array
    {
        $ran = [];

        foreach ($this->dueEvents($now) as $event) {
            $event->run();
            $ran[] = $event->getDescription();
        }

        return $ran;
    }

    /**
     * Splits a command string into tokens, respecting single and double
     * quoted segments.
     *
     * @param  string $command
     *
     * @return string[]
     */
    private function parseCommand(string $command): array
    {
        preg_match_all('/(?:[^\s"\']+|"[^"]*"|\'[^\']*\')+/', $command, $matches);

        return array_map(fn(string $t) => trim($t, '"\''), $matches[0]);
    }
}
