<?php

declare(strict_types=1);

namespace Zen\Console\Commands;

use Zen\Console\Command;
use Zen\Database\Migration;

/**
 * Displays a colour-coded table of all migration files and their run status.
 */
class MigrateStatusCommand extends Command
{
    /**
     * Injects the migration runner.
     *
     * @param  Migration $migration
     *
     * @return void
     */
    public function __construct(private readonly Migration $migration)
    {
    }

    /**
     * Returns the command name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'migrate:status';
    }

    /**
     * Returns a short description of what the command does.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Show the run/pending status of every migration file.';
    }

    /**
     * Fetches migration status rows and prints them as a formatted table.
     *
     * @param  string[] $args
     * @param  mixed[]  $options
     *
     * @return int Exit code 0 on success.
     */
    public function handle(array $args, array $options): int
    {
        $rows = $this->migration->status();

        if ($rows === []) {
            $this->warn('No migration files found.');

            return 0;
        }

        $this->line('');

        foreach ($rows as $row) {
            $isRun  = $row['status'] === 'run';
            $status = $isRun ? "\033[32mRan\033[0m    " : "\033[33mPending\033[0m";

            printf("  %s  %s\n", $status, $row['migration']);
        }

        $this->line('');

        return 0;
    }
}
