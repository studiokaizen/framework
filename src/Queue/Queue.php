<?php

declare(strict_types=1);

namespace Zen\Queue;

use Zen\Database\Connection;

/**
 * Database-backed FIFO queue for dispatching and consuming serialised Job
 * instances.
 */
class Queue
{
    /**
     * Injects the database connection.
     *
     * @param  Connection $db
     *
     * @return void
     */
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Serialises a job and inserts it into the jobs table, optionally delaying
     * its availability.
     *
     * @param  Job    $job   The job instance to queue.
     * @param  string $queue Queue name (defaults to 'default').
     * @param  int    $delay Seconds before the job becomes available.
     *
     * @return void
     */
    public function dispatch(Job $job, string $queue = 'default', int $delay = 0): void
    {
        $this->db->table('jobs')->insert([
            'queue'        => $queue,
            'payload'      => serialize($job),
            'attempts'     => 0,
            'available_at' => time() + $delay,
            'created_at'   => time(),
        ]);
    }

    /**
     * Atomically marks the oldest available job on the given queue as reserved
     * and returns it along with metadata, or returns null when the queue is
     * empty.
     *
     * @param  string $queue
     *
     * @return array{id: int, job: mixed, attempts: int, queue: string}|null
     */
    public function pop(string $queue = 'default'): ?array
    {
        $record = $this->db->table('jobs')
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->where('available_at', '<=', time())
            ->orderBy('id')
            ->first();

        if ($record === null) {
            return null;
        }

        $this->db->table('jobs')
            ->where('id', $record->id)
            ->update([
                'reserved_at' => time(),
                'attempts'    => $record->attempts + 1,
            ]);

        $job = @unserialize($record->payload);

        return [
            'id'       => $record->id,
            'job'      => $job,
            'attempts' => $record->attempts + 1,
            'queue'    => $record->queue,
        ];
    }

    /**
     * Permanently removes a job record from the queue table.
     *
     * @param  int $id Primary key of the job record.
     *
     * @return void
     */
    public function delete(int $id): void
    {
        $this->db->table('jobs')->where('id', $id)->delete();
    }

    /**
     * Clears the reservation on a job and makes it available again after an
     * optional delay.
     *
     * @param  int $id    Primary key of the job record.
     * @param  int $delay Seconds before the job is available again.
     *
     * @return void
     */
    public function release(int $id, int $delay = 0): void
    {
        $this->db->table('jobs')->where('id', $id)->update([
            'reserved_at'  => null,
            'available_at' => time() + $delay,
        ]);
    }

    /**
     * Copies the job payload to the failed_jobs table, then deletes the
     * original record.
     *
     * @param  int    $id        Primary key of the job record.
     * @param  string $exception Stringified exception including stack trace.
     * @param  string $queue     Queue name the job was on.
     *
     * @return void
     */
    public function fail(int $id, string $exception, string $queue): void
    {
        $record = $this->db->table('jobs')->find($id);

        if ($record !== null) {
            $this->db->table('failed_jobs')->insert([
                'queue'     => $queue,
                'payload'   => $record->payload,
                'exception' => $exception,
                'failed_at' => time(),
            ]);
        }

        $this->delete($id);
    }

    /**
     * Returns the number of jobs currently waiting in the named queue.
     *
     * @param  string $queue
     *
     * @return int
     */
    public function size(string $queue = 'default'): int
    {
        return $this->db->table('jobs')->where('queue', $queue)->count();
    }

    /**
     * Deletes all jobs from the named queue and returns the number of rows
     * removed.
     *
     * @param  string $queue
     *
     * @return int Number of deleted records.
     */
    public function clear(string $queue = 'default'): int
    {
        return $this->db->table('jobs')->where('queue', $queue)->delete();
    }
}
