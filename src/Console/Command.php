<?php

declare(strict_types=1);

namespace Zen\Console;

/**
 * Base class for all console commands; provides output helpers and defines the
 * command contract.
 */
abstract class Command
{
    /**
     * Returns the command name used on the CLI, e.g. 'migrate:fresh'.
     *
     * @return string
     */
    abstract public function name(): string;

    /**
     * Returns a one-line description shown in the command list.
     *
     * @return string
     */
    abstract public function description(): string;

    /**
     * Executes the command and returns an exit code (0 = success, 1 = error).
     *
     * @param  string[] $args    Positional arguments parsed from argv.
     * @param  mixed[]  $options Named options parsed from '--key=value' tokens.
     *
     * @return int Exit code.
     */
    abstract public function handle(array $args, array $options): int;

    /**
     * Writes a plain line to standard output.
     *
     * @param  string $text
     *
     * @return void
     */
    protected function line(string $text): void
    {
        echo $text . PHP_EOL;
    }

    /**
     * Writes a green-coloured informational line to standard output.
     *
     * @param  string $text
     *
     * @return void
     */
    protected function info(string $text): void
    {
        echo "\033[32m{$text}\033[0m" . PHP_EOL;
    }

    /**
     * Writes a yellow-coloured warning line to standard output.
     *
     * @param  string $text
     *
     * @return void
     */
    protected function warn(string $text): void
    {
        echo "\033[33m{$text}\033[0m" . PHP_EOL;
    }

    /**
     * Writes a red-coloured error line to standard output.
     *
     * @param  string $text
     *
     * @return void
     */
    protected function error(string $text): void
    {
        echo "\033[31m{$text}\033[0m" . PHP_EOL;
    }
}
