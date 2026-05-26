<?php

declare(strict_types=1);

namespace Zen\Console\Commands;

use Zen\Console\Command;
use Zen\Database\Migration;
use Zen\Database\Seeder;

/**
 * Rolls back every migration and re-runs them from scratch, optionally seeding
 * afterwards when --seed is passed.
 */
class MigrateFreshCommand extends Command
{
    /**
     * Injects the migration and seeder runners.
     *
     * @param  Migration $migration
     * @param  Seeder    $seeder
     *
     * @return void
     */
    public function __construct(
        private readonly Migration $migration,
        private readonly Seeder   $seeder,
    )
    {
    }

    /**
     * Returns the command name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'migrate:fresh';
    }

    /**
     * Returns a short description of what the command does.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Roll back all migrations then re-run them from scratch. Pass --seed to also run all seeders.';
    }

    /**
     * Rolls back all migrations, re-runs them, and optionally seeds the
     * database when the --seed flag is present.
     *
     * @param  string[] $args
     * @param  mixed[]  $options Pass ['seed' => true] to run seeders after.
     *
     * @return int Exit code 0 on success.
     */
    public function handle(array $args, array $options): int
    {
        $rolledBack = $this->migration->rollback(PHP_INT_MAX);

        foreach ($rolledBack as $name) {
            $this->warn("Rolled back: {$name}");
        }

        $executed = $this->migration->run();

        foreach ($executed as $name) {
            $this->info("Migrated: {$name}");
        }

        if ($executed !== []) {
            $this->info('Database refreshed.');
        }

        if (isset($options['seed'])) {
            $seeded = $this->seeder->run();

            foreach ($seeded as $name) {
                $this->info("Seeded: {$name}");
            }
        }

        return 0;
    }
}
