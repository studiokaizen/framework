<?php

declare(strict_types=1);

namespace Zen\Queue;

use Throwable;

/**
 * Long-running queue consumer that pops and processes jobs in an infinite
 * loop, handling retries and failures automatically.
 */
class Worker
{
    /**
     * Injects the queue instance used to pop, delete, release, and fail jobs.
     *
     * @param  Queue $queue
     *
     * @return void
     */
    public function __construct(private readonly Queue $queue)
    {
    }

    /**
     * Starts the processing loop, sleeping when the queue is empty and
     * stopping early when a maximum job count is specified.
     *
     * @param  string $queue   Queue name to consume from.
     * @param  int    $sleep   Seconds to sleep when no jobs are available.
     * @param  int    $maxJobs Stop after processing this many jobs; 0 = run
     *                         forever.
     *
     * @return void
     */
    public function run(string $queue = 'default', int $sleep = 3, int $maxJobs = 0): void
    {
        $processed = 0;

        while (true) {
            $item = $this->queue->pop($queue);

            if ($item === null) {
                sleep($sleep);
                continue;
            }

            $this->process($item);
            $processed++;

            if ($maxJobs > 0 && $processed >= $maxJobs) {
                break;
            }
        }
    }

    /**
     * Executes a single job item, deleting it on success or releasing/failing
     * it according to the job's retry configuration.
     *
     * @param  array{id: int, job: mixed, attempts: int, queue: string} $item
     *
     * @return void
     */
    private function process(array $item): void
    {
        $id       = $item['id'];
        $job      = $item['job'];
        $attempts = $item['attempts'];
        $queue    = $item['queue'];

        if (!($job instanceof Job)) {
            $this->queue->fail($id, 'Invalid payload: not an instance of Job.', $queue);
            return;
        }

        try {
            $job->handle();
            $this->queue->delete($id);
        } catch (Throwable $e) {
            $exception = $e->getMessage() . "\n" . $e->getTraceAsString();

            if ($attempts >= $job->tries) {
                $this->queue->fail($id, $exception, $queue);
            } else {
                $this->queue->release($id, $job->retryAfter);
            }
        }
    }
}
