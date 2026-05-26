<?php

declare(strict_types=1);

namespace Zen\Console\Commands;

use DateTimeImmutable;
use Zen\Console\Command;
use Zen\Scheduling\Scheduler;

/**
 * Runs all scheduled tasks whose cron expression matches the current time.
 * Intended to be called every minute from the system cron.
 */
class ScheduleRunCommand extends Command
{
    /**
     * Injects the scheduler instance.
     *
     * @param  Scheduler $scheduler
     *
     * @return void
     */
    public function __construct(private readonly Scheduler $scheduler)
    {
    }

    /**
     * Returns the command name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'schedule:run';
    }

    /**
     * Returns a short description of what the command does.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Run scheduled tasks that are due.';
    }

    /**
     * Collects due events, executes each one, and prints a summary.
     *
     * @param  string[] $args
     * @param  mixed[]  $options
     *
     * @return int Exit code 0 on success.
     */
    public function handle(array $args, array $options): int
    {
        $now  = new DateTimeImmutable();
        $due  = $this->scheduler->dueEvents($now);

        if ($due === []) {
            $this->line('No scheduled tasks are due.');

            return 0;
        }

        foreach ($due as $event) {
            $this->info('Running: ' . $event->getDescription());
            $event->run();
        }

        $this->info(count($due) . ' task(s) ran.');

        return 0;
    }
}
