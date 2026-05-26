<?php

declare(strict_types=1);

namespace Zen\Database;

/**
 * Discovers, runs, and rolls back SQL migration files stored in a given
 * directory, recording run migrations in a `migrations` table.
 */
class Migration
{
    /**
     * Creates the migration runner with a database connection and the path to
     * migration SQL files.
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
     * Runs all pending migrations in filename order and returns the names of
     * the files that were executed.
     *
     * @return array<int, string>
     */
    public function run(): array
    {
        $this->ensureMigrationsTable();

        $executed = [];

        foreach ($this->pending() as $file) {
            $this->runFile($file, 'up');

            $name = basename($file);

            $this->connection->insert(
                'INSERT INTO migrations (migration, run_at) VALUES (?, ?)',
                [$name, date('Y-m-d H:i:s')],
            );

            $executed[] = $name;
        }

        return $executed;
    }

    /**
     * Rolls back the most recently run migrations and returns their names.
     *
     * @param  int $steps  Number of migrations to roll back.
     *
     * @return array<int, string>
     */
    public function rollback(int $steps = 1): array
    {
        $this->ensureMigrationsTable();

        $ran      = $this->connection->select(
            'SELECT migration FROM migrations ORDER BY id DESC LIMIT ?',
            [$steps],
        );
        $rolledBack = [];

        foreach ($ran as $row) {
            $file = $this->path . DIRECTORY_SEPARATOR . $row->migration;

            if (file_exists($file)) {
                $this->runFile($file, 'down');
            }

            $this->connection->delete(
                'DELETE FROM migrations WHERE migration = ?',
                [$row->migration],
            );

            $rolledBack[] = $row->migration;
        }

        return $rolledBack;
    }

    /**
     * Returns the run/pending status for every discovered migration file.
     *
     * @return array<int, array{migration: string, status: string}>
     */
    public function status(): array
    {
        $this->ensureMigrationsTable();

        $ran    = array_column($this->connection->select('SELECT migration FROM migrations'), 'migration');
        $result = [];

        foreach ($this->discoverFiles() as $file) {
            $name     = basename($file);
            $result[] = [
                'migration' => $name,
                'status'    => in_array($name, $ran, true) ? 'run' : 'pending',
            ];
        }

        return $result;
    }

    /**
     * Returns the paths of migration files that have not yet been run.
     *
     * @return array<int, string>
     */
    private function pending(): array
    {
        $ran = array_column(
            $this->connection->select('SELECT migration FROM migrations'),
            'migration',
        );

        return array_values(array_filter(
            $this->discoverFiles(),
            fn(string $file): bool => !in_array(basename($file), $ran, true),
        ));
    }

    /**
     * Returns all migration SQL file paths sorted alphabetically.
     *
     * @return array<int, string>
     */
    private function discoverFiles(): array
    {
        $files = glob($this->path . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($files);

        return $files;
    }

    /**
     * Reads a migration file and executes either its UP or DOWN section.
     *
     * @param  string $file
     * @param  string $direction  'up' or 'down'.
     *
     * @return void
     */
    private function runFile(string $file, string $direction): void
    {
        $content    = file_get_contents($file);
        $hasMarkers = (bool) preg_match('/^--\s*(UP|DOWN)\b/im', $content);

        if ($hasMarkers) {
            $sql = $this->extractSection($content, $direction);
        } elseif ($direction === 'up') {
            $sql = $content;
        } else {
            return;
        }

        foreach ($this->splitStatements($sql) as $statement) {
            $this->connection->unprepared($statement);
        }
    }

    /**
     * Extracts the SQL lines that belong to the given direction section
     * (-- UP or -- DOWN) from the file content.
     *
     * @param  string $content
     * @param  string $direction
     *
     * @return string
     */
    private function extractSection(string $content, string $direction): string
    {
        $direction = strtoupper($direction);
        $lines     = explode("\n", $content);
        $collecting = false;
        $result    = [];

        foreach ($lines as $line) {
            if (preg_match('/^--\s*(UP|DOWN)\s*$/i', trim($line), $m)) {
                $collecting = strtoupper($m[1]) === $direction;
                continue;
            }

            if ($collecting) {
                $result[] = $line;
            }
        }

        return trim(implode("\n", $result));
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

    /**
     * Creates the migrations tracking table if it does not already exist.
     *
     * @return void
     */
    private function ensureMigrationsTable(): void
    {
        $this->connection->unprepared(
            'CREATE TABLE IF NOT EXISTS migrations (
                id        INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) NOT NULL,
                run_at    DATETIME NOT NULL
            )',
        );
    }
}
