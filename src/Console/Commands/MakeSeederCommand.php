<?php

declare(strict_types=1);

namespace Zen\Console\Commands;

use Zen\Console\Command;

/**
 * Scaffolds a new numbered SQL seeder file in the seeders directory.
 */
class MakeSeederCommand extends Command
{
    /**
     * Absolute path to the seeders directory.
     *
     * @param  string $seedersPath
     *
     * @return void
     */
    public function __construct(private readonly string $seedersPath)
    {
    }

    /**
     * Returns the command name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'make:seeder';
    }

    /**
     * Returns a short description of what the command does.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Create a new seeder file.';
    }

    /**
     * Creates a sequentially numbered seeder file from the given name,
     * creating the seeders directory if it does not yet exist.
     *
     * @param  string[] $args    First element is the seeder name.
     * @param  mixed[]  $options Unused.
     *
     * @return int Exit code: 0 on success, 1 on validation error.
     */
    public function handle(array $args, array $options): int
    {
        $name = trim($args[0] ?? '');

        if ($name === '') {
            $this->error('Please provide a seeder name.');

            return 1;
        }

        $name     = strtolower(str_replace([' ', '-'], '_', $name));
        $filename = sprintf('%04d_%s.sql', $this->nextSequence(), $name);
        $path     = $this->seedersPath . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($path)) {
            $this->error("Seeder already exists: {$filename}");

            return 1;
        }

        if (!is_dir($this->seedersPath)) {
            mkdir($this->seedersPath, 0755, true);
        }

        file_put_contents($path, '');

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
        $files = glob($this->seedersPath . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        $max   = 0;

        foreach ($files as $file) {
            if (preg_match('/^(\d+)_/', basename($file), $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max + 1;
    }
}
