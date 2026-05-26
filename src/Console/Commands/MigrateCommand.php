<?php

declare(strict_types=1);

namespace Zen\Console\Commands;

use Zen\Console\Command;
use Zen\Database\Migration;

/**
 * Runs every pending database migration in order.
 */
class MigrateCommand extends Command
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
        return 'migrate';
    }

    /**
     * Returns a short description of what the command does.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Run all pending database migrations.';
    }

    /**
     * Executes pending migrations and prints the name of each one run.
     *
     * @param  string[] $args
     * @param  mixed[]  $options
     *
     * @return int Exit code 0 on success.
     */
    public function handle(array $args, array $options): int
    {
        $executed = $this->migration->run();

        if ($executed === []) {
            $this->warn('Nothing to migrate.');

            return 0;
        }

        foreach ($executed as $name) {
            $this->info("Migrated: {$name}");
        }

        return 0;
    }
}
