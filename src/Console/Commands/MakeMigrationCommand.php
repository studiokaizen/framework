<?php

declare(strict_types=1);

namespace Zen\Console\Commands;

use Zen\Console\Command;

/**
 * Scaffolds a new numbered SQL migration file with empty UP and DOWN sections.
 */
class MakeMigrationCommand extends Command
{
    /**
     * Absolute path to the migrations directory.
     *
     * @param  string $migrationsPath
     *
     * @return void
     */
    public function __construct(private readonly string $migrationsPath)
    {
    }

    /**
     * Returns the command name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'make:migration';
    }

    /**
     * Returns a short description of what the command does.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Create a new migration file.';
    }

    /**
     * Creates a sequentially numbered migration file from the given name.
     *
     * @param  string[] $args    First element is the migration name.
     * @param  mixed[]  $options Unused.
     *
     * @return int Exit code: 0 on success, 1 on validation error.
     */
    public function handle(array $args, array $options): int
    {
        $name = trim($args[0] ?? '');

        if ($name === '') {
            $this->error('Please provide a migration name.');

            return 1;
        }

        $name     = strtolower(str_replace([' ', '-'], '_', $name));
        $filename = sprintf('%04d_%s.sql', $this->nextSequence(), $name);
        $path     = $this->migrationsPath . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($path)) {
            $this->error("Migration already exists: {$filename}");

            return 1;
        }

        file_put_contents($path, $this->stub());

        $this->info("Created: {$filename}");

        return 0;
    }

    /**
     * Returns the next available sequential number by scanning existing files.
     *
     * @return int
     */
    private function nextSequence(): int
    {
        $files = glob($this->migrationsPath . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        $max   = 0;

        foreach ($files as $file) {
            if (preg_match('/^(\d+)_/', basename($file), $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max + 1;
    }

    /**
     * Returns the template content written into every new migration file.
     *
     * @return string
     */
    private function stub(): string
    {
        return "-- UP\n\n\n\n-- DOWN\n\n\n";
    }
}
