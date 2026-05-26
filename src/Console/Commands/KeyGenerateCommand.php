<?php

declare(strict_types=1);

namespace Zen\Console\Commands;

use Zen\Console\Command;

/**
 * Generates a random 32-byte hex key suitable for use as the application
 * encryption key.
 */
class KeyGenerateCommand extends Command
{
    /**
     * Returns the command name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'key:generate';
    }

    /**
     * Returns a short description of what the command does.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Generate a new 32-byte application encryption key.';
    }

    /**
     * Generates and prints a new key, reminding the user where to copy it.
     *
     * @param  string[] $args
     * @param  mixed[]  $options
     *
     * @return int Exit code 0 on success.
     */
    public function handle(array $args, array $options): int
    {
        $key = bin2hex(random_bytes(16));

        $this->info("Generated key: {$key}");
        $this->line("Copy this value to config.php under 'app.key'.");

        return 0;
    }
}
