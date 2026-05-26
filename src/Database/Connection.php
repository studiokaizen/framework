<?php

declare(strict_types=1);

namespace Zen\Database;

use PDO;
use Throwable;

/**
 * Wraps a PDO connection with convenience methods for executing queries and
 * managing transactions, supporting both SQLite and MySQL drivers.
 */
class Connection
{
    /**
     * The underlying PDO instance, created lazily on first use.
     *
     * @var PDO|null
     */
    private ?PDO $pdo = null;

    /**
     * Creates the connection wrapper with driver configuration.
     *
     * @param  array<string, mixed> $config
     *
     * @return void
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * Returns the PDO instance, creating it on first call.
     *
     * @return PDO
     */
    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = new PDO(
                $this->buildDsn(),
                $this->config['username'] ?? null,
                $this->config['password'] ?? null,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ],
            );
        }

        return $this->pdo;
    }

    /**
     * Returns a QueryBuilder scoped to the given table name.
     *
     * @param  string $table
     *
     * @return QueryBuilder
     */
    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    /**
     * Executes a SELECT statement and returns all matching rows.
     *
     * @param  string               $sql
     * @param  array<int, mixed>    $bindings
     *
     * @return array<int, object>
     */
    public function select(string $sql, array $bindings = []): array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchAll();
    }

    /**
     * Executes a SELECT statement and returns the first row, or null.
     *
     * @param  string            $sql
     * @param  array<int, mixed> $bindings
     *
     * @return mixed
     */
    public function selectOne(string $sql, array $bindings = []): mixed
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetch() ?: null;
    }

    /**
     * Executes an INSERT statement and returns the last insert ID as a string.
     *
     * @param  string            $sql
     * @param  array<int, mixed> $bindings
     *
     * @return string
     */
    public function insert(string $sql, array $bindings = []): string
    {
        $this->statement($sql, $bindings);

        return $this->pdo()->lastInsertId();
    }

    /**
     * Executes an UPDATE statement and returns the number of affected rows.
     *
     * @param  string            $sql
     * @param  array<int, mixed> $bindings
     *
     * @return int
     */
    public function update(string $sql, array $bindings = []): int
    {
        return $this->affectingStatement($sql, $bindings);
    }

    /**
     * Executes a DELETE statement and returns the number of affected rows.
     *
     * @param  string            $sql
     * @param  array<int, mixed> $bindings
     *
     * @return int
     */
    public function delete(string $sql, array $bindings = []): int
    {
        return $this->affectingStatement($sql, $bindings);
    }

    /**
     * Prepares and executes an arbitrary SQL statement and returns success.
     *
     * @param  string            $sql
     * @param  array<int, mixed> $bindings
     *
     * @return bool
     */
    public function statement(string $sql, array $bindings = []): bool
    {
        $stmt = $this->pdo()->prepare($sql);

        return $stmt->execute($bindings);
    }

    /**
     * Executes a raw SQL string without parameter binding and returns success.
     *
     * @param  string $sql
     *
     * @return bool
     */
    public function unprepared(string $sql): bool
    {
        return $this->pdo()->exec($sql) !== false;
    }

    /**
     * Wraps a callback in a database transaction, rolling back on any
     * exception.
     *
     * @param  callable $callback
     *
     * @throws Throwable If the callback throws.
     *
     * @return mixed
     */
    public function transaction(callable $callback): mixed
    {
        $this->pdo()->beginTransaction();

        try {
            $result = $callback($this);
            $this->pdo()->commit();

            return $result;
        } catch (Throwable $e) {
            $this->pdo()->rollBack();

            throw $e;
        }
    }

    /**
     * Returns the configured database driver name (e.g. 'mysql', 'sqlite').
     *
     * @return string
     */
    public function getDriver(): string
    {
        return $this->config['driver'] ?? 'mysql';
    }

    /**
     * Builds the PDO DSN string from the configuration.
     *
     * @return string
     */
    private function buildDsn(): string
    {
        if (($this->config['driver'] ?? 'mysql') === 'sqlite') {
            return 'sqlite:' . $this->config['database'];
        }

        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host']     ?? '127.0.0.1',
            $this->config['port']     ?? 3306,
            $this->config['database'] ?? '',
            $this->config['charset']  ?? 'utf8mb4',
        );
    }

    /**
     * Executes a statement and returns the number of affected rows.
     *
     * @param  string            $sql
     * @param  array<int, mixed> $bindings
     *
     * @return int
     */
    private function affectingStatement(string $sql, array $bindings): int
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }
}
