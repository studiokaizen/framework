<?php

declare(strict_types=1);

namespace Zen\Console\Commands;

use Zen\Cache\Cache;
use Zen\Console\Command;

/**
 * Flushes all entries from the application file cache.
 */
class CacheClearCommand extends Command
{
    /**
     * Injects the cache instance that will be flushed.
     *
     * @param  Cache $cache
     *
     * @return void
     */
    public function __construct(private readonly Cache $cache)
    {
    }

    /**
     * Returns the command name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'cache:clear';
    }

    /**
     * Returns a short description of what the command does.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Clear all entries from the application cache.';
    }

    /**
     * Flushes the cache and prints a confirmation message.
     *
     * @param  string[] $args
     * @param  mixed[]  $options
     *
     * @return int Exit code 0 on success.
     */
    public function handle(array $args, array $options): int
    {
        $this->cache->flush();
        $this->info('Cache cleared successfully.');

        return 0;
    }
}
