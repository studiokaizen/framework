<?php

declare(strict_types=1);

namespace Zen\Console\Commands;

use Zen\Console\Command;
use Zen\Database\Migration;

/**
 * Rolls back the last batch of migrations, or N migrations when --steps=N is
 * supplied.
 */
class MigrateRollbackCommand extends Command
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
        return 'migrate:rollback';
    }

    /**
     * Returns a short description of what the command does.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Roll back the last batch of migrations. Use --steps=N to roll back N migrations.';
    }

    /**
     * Rolls back the requested number of migrations and prints each one.
     *
     * @param  string[] $args
     * @param  mixed[]  $options Pass ['steps' => N] to roll back N steps.
     *
     * @return int Exit code 0 on success.
     */
    public function handle(array $args, array $options): int
    {
        $steps      = (int) ($options['steps'] ?? 1);
        $rolledBack = $this->migration->rollback($steps);

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
