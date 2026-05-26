<?php

declare(strict_types=1);

namespace Zen\Console;

/**
 * CLI application that registers commands and dispatches argv to the matching
 * handler.
 */
class Console
{
    /**
     * Registered commands keyed by their name string.
     *
     * @var array<string, Command>
     */
    private array $commands = [];

    /**
     * Registers one or more commands, indexing them by their name.
     *
     * @param  Command ...$commands
     *
     * @return static
     */
    public function add(Command ...$commands): static
    {
        foreach ($commands as $command) {
            $this->commands[$command->name()] = $command;
        }

        return $this;
    }

    /**
     * Resolves the command from argv[1], parses the remaining tokens into args
     * and options, and dispatches to the command's handle() method.
     *
     * @param  string[] $argv Raw argument vector from the CLI entry point.
     *
     * @return int Exit code returned by the command (or 1 on unknown command).
     */
    public function run(array $argv): int
    {
        $name = $argv[1] ?? null;

        if ($name === null || $name === 'list') {
            return $this->list();
        }

        if (!isset($this->commands[$name])) {
            echo "\033[31mCommand \"{$name}\" not found.\033[0m" . PHP_EOL;

            return 1;
        }

        [$args, $options] = $this->parseArgv($argv);

        return $this->commands[$name]->handle($args, $options);
    }

    /**
     * Prints the ZenPHP banner followed by a padded list of all registered
     * commands and their descriptions.
     *
     * @return int Always returns 0.
     */
    private function list(): int
    {
        echo PHP_EOL;
        echo "\033[32mZenPHP Console\033[0m" . PHP_EOL;
        echo PHP_EOL;
        echo "Usage: php zen <command> [options]" . PHP_EOL;
        echo PHP_EOL;
        echo "Available commands:" . PHP_EOL;

        foreach ($this->commands as $command) {
            printf("  \033[33m%-28s\033[0m %s\n", $command->name(), $command->description());
        }

        echo PHP_EOL;

        return 0;
    }

    /**
     * Splits argv tokens (starting at index 2) into positional arguments and
     * named options.  '--flag' becomes ['flag' => true] and '--key=val' becomes
     * ['key' => 'val'].
     *
     * @param  string[] $argv
     *
     * @return array{0: string[], 1: array<string, mixed>}
     */
    private function parseArgv(array $argv): array
    {
        $args    = [];
        $options = [];

        foreach (array_slice($argv, 2) as $token) {
            if (str_starts_with($token, '--')) {
                $token = substr($token, 2);

                if (str_contains($token, '=')) {
                    [$key, $value] = explode('=', $token, 2);
                    $options[$key] = $value;
                } else {
                    $options[$token] = true;
                }
            } else {
                $args[] = $token;
            }
        }

        return [$args, $options];
    }
}
