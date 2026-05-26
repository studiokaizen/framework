<?php

declare(strict_types=1);

namespace Zen\Queue;

/**
 * Base class for all queued jobs; subclasses implement handle() with the
 * job-specific logic.
 */
abstract class Job
{
    /**
     * Maximum number of times the job will be attempted before it is moved
     * to the failed_jobs table.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * Number of seconds to wait before re-queuing a failed attempt.
     *
     * @var int
     */
    public int $retryAfter = 60;

    /**
     * Executes the job's work.
     *
     * @return void
     */
    abstract public function handle(): void;
}
