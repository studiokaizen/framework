<?php

declare(strict_types=1);

namespace Zen\Console\Commands;

use Zen\Console\Command;
use Zen\Database\Seeder;

/**
 * Runs all database seeders found in the seeders directory.
 */
class DbSeedCommand extends Command
{
    /**
     * Injects the seeder runner.
     *
     * @param  Seeder $seeder
     *
     * @return void
     */
    public function __construct(private readonly Seeder $seeder)
    {
    }

    /**
     * Returns the command name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'db:seed';
    }

    /**
     * Returns a short description of what the command does.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Run all database seeders.';
    }

    /**
     * Executes all seeders and prints each seeded file name.
     *
     * @param  string[] $args
     * @param  mixed[]  $options
     *
     * @return int Exit code 0 on success.
     */
    public function handle(array $args, array $options): int
    {
        $seeded = $this->seeder->run();

        if ($seeded === []) {
            $this->warn('No seeders found.');

            return 0;
        }

        foreach ($seeded as $name) {
            $this->info("Seeded: {$name}");
        }

        return 0;
    }
}
