<?php

declare(strict_types=1);

namespace Zen\Database;

/**
 * Runs all SQL seeder files found in a given directory against the database
 * connection.
 */
class Seeder
{
    /**
     * Creates the seeder with a database connection and the path to SQL seeder
     * files.
     *
     * @param  Connection $connection
     * @param  string     $path
     *
     * @return void
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly string     $path,
    )
    {
    }

    /**
     * Executes every SQL statement in every seeder file and returns the
     * names of the files that were run.
     *
     * @return array<int, string>
     */
    public function run(): array
    {
        $files = glob($this->path . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($files);

        $seeded = [];

        foreach ($files as $file) {
            foreach ($this->splitStatements((string) file_get_contents($file)) as $statement) {
                $this->connection->unprepared($statement);
            }

            $seeded[] = basename($file);
        }

        return $seeded;
    }

    /**
     * Splits a SQL string on semicolons and returns non-empty statement
     * strings.
     *
     * @param  string $sql
     *
     * @return array<int, string>
     */
    private function splitStatements(string $sql): array
    {
        return array_values(array_filter(
            array_map('trim', explode(';', $sql)),
            fn(string $s): bool => $s !== '',
        ));
    }
}
