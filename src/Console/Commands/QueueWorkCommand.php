<?php

declare(strict_types=1);

namespace Zen\Console\Commands;

use Zen\Console\Command;
use Zen\Queue\Worker;

/**
 * Starts the queue worker loop, processing jobs from the database queue.
 */
class QueueWorkCommand extends Command
{
    /**
     * Injects the queue worker.
     *
     * @param  Worker $worker
     *
     * @return void
     */
    public function __construct(private readonly Worker $worker)
    {
    }

    /**
     * Returns the command name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'queue:work';
    }

    /**
     * Returns a short description of what the command does.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Process jobs from the queue.';
    }

    /**
     * Starts the worker with queue name, sleep interval, and optional job
     * limit taken from the options map.
     *
     * @param  string[] $args
     * @param  mixed[]  $options Supported: queue, sleep, max-jobs.
     *
     * @return int Exit code 0 on success.
     */
    public function handle(array $args, array $options): int
    {
        $queue   = $options['queue']    ?? 'default';
        $sleep   = (int) ($options['sleep']    ?? 3);
        $maxJobs = (int) ($options['max-jobs'] ?? 0);

        $this->info("Processing jobs on [{$queue}]...");

        $this->worker->run($queue, $sleep, $maxJobs);

        return 0;
    }
}
