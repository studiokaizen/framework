<?php

declare(strict_types=1);

namespace Zen\Database;

/**
 * A fluent SQL query builder that composes SELECT, INSERT, UPDATE, and DELETE
 * statements for a single table.
 */
class QueryBuilder
{
    /**
     * Columns to include in the SELECT clause.
     *
     * @var array<int, string>
     */
    private array $columns = ['*'];

    /**
     * WHERE clause fragments with their joining boolean.
     *
     * @var array<int, array{sql: string, boolean: string}>
     */
    private array $wheres = [];

    /**
     * ORDER BY clause fragments.
     *
     * @var array<int, string>
     */
    private array $orders = [];

    /**
     * Maximum number of rows to return.
     *
     * @var int|null
     */
    private ?int $limit = null;

    /**
     * Number of rows to skip.
     *
     * @var int|null
     */
    private ?int $offset = null;

    /**
     * Positional parameter bindings for the WHERE clause.
     *
     * @var array<int, mixed>
     */
    private array $bindings = [];

    /**
     * Creates the builder scoped to the given connection and table.
     *
     * @param  Connection $connection
     * @param  string     $table
     *
     * @return void
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly string     $table,
    )
    {
    }

    /**
     * Sets the columns to return in the SELECT clause.
     *
     * @param  string ...$columns
     *
     * @return static
     */
    public function select(string ...$columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Adds an AND WHERE condition.
     *
     * When called with two arguments `where('col', $value)`, the operator
     * defaults to `=`. With three arguments, the second is the operator.
     *
     * @param  string $column
     * @param  mixed  $operatorOrValue
     * @param  mixed  $value
     *
     * @return static
     */
    public function where(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        return $this->addWhere($column, $operatorOrValue, $value, 'AND');
    }

    /**
     * Adds an OR WHERE condition using the same two- or three-argument
     * convention as `where()`.
     *
     * @param  string $column
     * @param  mixed  $operatorOrValue
     * @param  mixed  $value
     *
     * @return static
     */
    public function orWhere(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        return $this->addWhere($column, $operatorOrValue, $value, 'OR');
    }

    /**
     * Adds a WHERE IN condition.
     *
     * @param  string            $column
     * @param  array<int, mixed> $values
     *
     * @return static
     */
    public function whereIn(string $column, array $values): static
    {
        $placeholders   = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = ['sql' => "{$column} IN ({$placeholders})", 'boolean' => 'AND'];

        array_push($this->bindings, ...$values);

        return $this;
    }

    /**
     * Adds a WHERE NOT IN condition.
     *
     * @param  string            $column
     * @param  array<int, mixed> $values
     *
     * @return static
     */
    public function whereNotIn(string $column, array $values): static
    {
        $placeholders   = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = ['sql' => "{$column} NOT IN ({$placeholders})", 'boolean' => 'AND'];

        array_push($this->bindings, ...$values);

        return $this;
    }

    /**
     * Adds a WHERE column IS NULL condition.
     *
     * @param  string $column
     *
     * @return static
     */
    public function whereNull(string $column): static
    {
        $this->wheres[] = ['sql' => "{$column} IS NULL", 'boolean' => 'AND'];

        return $this;
    }

    /**
     * Adds a WHERE column IS NOT NULL condition.
     *
     * @param  string $column
     *
     * @return static
     */
    public function whereNotNull(string $column): static
    {
        $this->wheres[] = ['sql' => "{$column} IS NOT NULL", 'boolean' => 'AND'];

        return $this;
    }

    /**
     * Adds an ORDER BY clause.
     *
     * @param  string $column
     * @param  string $direction  'ASC' or 'DESC'.
     *
     * @return static
     */
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orders[] = "{$column} " . strtoupper($direction);

        return $this;
    }

    /**
     * Sets a LIMIT on the number of rows returned.
     *
     * @param  int $limit
     *
     * @return static
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Sets an OFFSET for the rows returned.
     *
     * @param  int $offset
     *
     * @return static
     */
    public function offset(int $offset): static
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Executes the SELECT query and returns all matching rows.
     *
     * @return array<int, object>
     */
    public function get(): array
    {
        return $this->connection->select($this->toSelectSql(), $this->bindings);
    }

    /**
     * Returns the first matching row, or null when none exist.
     *
     * @return mixed
     */
    public function first(): mixed
    {
        return $this->limit(1)->connection->selectOne($this->toSelectSql(), $this->bindings);
    }

    /**
     * Finds a row by its primary key value and returns it, or null.
     *
     * @param  mixed  $id
     * @param  string $primaryKey
     *
     * @return mixed
     */
    public function find(mixed $id, string $primaryKey = 'id'): mixed
    {
        return $this->where($primaryKey, $id)->first();
    }

    /**
     * Paginates the result set and returns a Paginator instance.
     *
     * @param  int $perPage
     * @param  int $page
     *
     * @return Paginator
     */
    public function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        $page  = max(1, $page);
        $total = $this->count();
        $items = $this->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return new Paginator($items, $total, $perPage, $page);
    }

    /**
     * Returns the total number of rows matching the current WHERE clause.
     *
     * @return int
     */
    public function count(): int
    {
        $sql    = "SELECT COUNT(*) AS aggregate FROM {$this->table}" . $this->buildWhereClause();
        $result = $this->connection->selectOne($sql, $this->bindings);

        return (int) ($result->aggregate ?? 0);
    }

    /**
     * Returns true when at least one row matches the current WHERE clause.
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Inserts a row and returns the last insert ID as a string.
     *
     * @param  array<string, mixed> $data
     *
     * @return string
     */
    public function insert(array $data): string
    {
        $columns      = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        return $this->connection->insert(
            "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})",
            array_values($data),
        );
    }

    /**
     * Inserts a row and returns the last insert ID cast to int.
     *
     * @param  array<string, mixed> $data
     *
     * @return int
     */
    public function insertGetId(array $data): int
    {
        return (int) $this->insert($data);
    }

    /**
     * Updates rows matching the current WHERE clause and returns the affected
     * row count.
     *
     * @param  array<string, mixed> $data
     *
     * @return int
     */
    public function update(array $data): int
    {
        $set = implode(', ', array_map(fn(string $col): string => "{$col} = ?", array_keys($data)));

        return $this->connection->update(
            "UPDATE {$this->table} SET {$set}" . $this->buildWhereClause(),
            [...array_values($data), ...$this->bindings],
        );
    }

    /**
     * Deletes rows matching the current WHERE clause and returns the affected
     * row count.
     *
     * @return int
     */
    public function delete(): int
    {
        return $this->connection->delete(
            "DELETE FROM {$this->table}" . $this->buildWhereClause(),
            $this->bindings,
        );
    }

    /**
     * Adds a WHERE condition with the given boolean connector.
     *
     * @param  string $column
     * @param  mixed  $operatorOrValue
     * @param  mixed  $value
     * @param  string $boolean
     *
     * @return static
     */
    private function addWhere(string $column, mixed $operatorOrValue, mixed $value, string $boolean): static
    {
        if ($value === null) {
            $value    = $operatorOrValue;
            $operator = '=';
        } else {
            $operator = (string) $operatorOrValue;
        }

        $this->wheres[]   = ['sql' => "{$column} {$operator} ?", 'boolean' => $boolean];
        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Builds the SQL WHERE clause string from the accumulated conditions.
     *
     * @return string
     */
    private function buildWhereClause(): string
    {
        if ($this->wheres === []) {
            return '';
        }

        $parts = [];

        foreach ($this->wheres as $i => $where) {
            $parts[] = ($i > 0 ? $where['boolean'] . ' ' : '') . $where['sql'];
        }

        return ' WHERE ' . implode(' ', $parts);
    }

    /**
     * Builds the full SELECT SQL string including columns, table, WHERE,
     * ORDER BY, LIMIT, and OFFSET.
     *
     * @return string
     */
    private function toSelectSql(): string
    {
        $sql  = 'SELECT ' . implode(', ', $this->columns) . " FROM {$this->table}";
        $sql .= $this->buildWhereClause();

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }
}
