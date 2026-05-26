<?php

declare(strict_types=1);

namespace Zen\Console\Commands;

use Zen\Console\Command;
use Zen\Database\Migration;

/**
 * Rolls back every migration that has been run, leaving the database empty.
 */
class MigrateResetCommand extends Command
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
        return 'migrate:reset';
    }

    /**
     * Returns a short description of what the command does.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Roll back every migration that has been run.';
    }

    /**
     * Rolls back all migrations and prints the name of each one reversed.
     *
     * @param  string[] $args
     * @param  mixed[]  $options
     *
     * @return int Exit code 0 on success.
     */
    public function handle(array $args, array $options): int
    {
        $rolledBack = $this->migration->rollback(PHP_INT_MAX);

        if ($rolledBack === []) {
            $this->warn('Nothing to roll back.');

            return 0;
        }

        foreach ($rolledBack as $name) {
            $this->warn("Rolled back: {$name}");
        }

        return 0;
    }
}
