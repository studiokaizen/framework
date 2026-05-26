<?php

declare(strict_types=1);

namespace Zen\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;

/**
 * An immutable-style, chainable wrapper around a PHP array providing
 * higher-order operations for filtering, mapping, sorting, and grouping.
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * The underlying items array.
     *
     * @var array<mixed>
     */
    protected array $items = [];

    /**
     * Creates the collection with an optional initial items array.
     *
     * @param  array<mixed> $items
     *
     * @return void
     */
    public function __construct(protected array $items = [])
    {
    }

    /**
     * Creates a collection from an array, another collection, or a scalar
     * value.
     *
     * @param  mixed $items
     *
     * @return static
     */
    public static function make(mixed $items = []): static
    {
        return new static(match (true) {
            $items instanceof self => $items->all(),
            is_array($items)      => $items,
            default               => [$items],
        });
    }

    /**
     * Creates a collection from any iterable.
     *
     * @param  iterable<mixed> $items
     *
     * @return static
     */
    public static function from(iterable $items): static
    {
        return new static($items instanceof \Traversable ? iterator_to_array($items) : $items);
    }

    /**
     * Returns the underlying items array.
     *
     * @return array<mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Returns the number of items in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Returns true when the collection contains no items.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Returns true when the collection contains at least one item.
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Returns the item at the given key, or the default if absent.
     *
     * @param  string|int $key
     * @param  mixed      $default
     *
     * @return mixed
     */
    public function get(string|int $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    /**
     * Returns true when all of the given keys are present.
     *
     * @param  string|int ...$keys
     *
     * @return bool
     */
    public function has(string|int ...$keys): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $this->items)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the first item that passes the optional callback, or the
     * default.
     *
     * @param  callable|null $callback
     * @param  mixed         $default
     *
     * @return mixed
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        return Arr::first($this->items, $callback, $default);
    }

    /**
     * Returns the last item that passes the optional callback, or the default.
     *
     * @param  callable|null $callback
     * @param  mixed         $default
     *
     * @return mixed
     */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        return Arr::last($this->items, $callback, $default);
    }

    /**
     * Returns a new collection containing only the keys of this collection.
     *
     * @return static
     */
    public function keys(): static
    {
        return new static(array_keys($this->items));
    }

    /**
     * Returns a new collection with the items re-indexed from zero.
     *
     * @return static
     */
    public function values(): static
    {
        return new static(array_values($this->items));
    }

    /**
     * Returns a new collection with all keys flattened to dot notation.
     *
     * @return static
     */
    public function dot(): static
    {
        return new static(Arr::dot($this->items));
    }

    /**
     * Returns a new collection with dot-notated keys expanded back to nested
     * arrays.
     *
     * @return static
     */
    public function undot(): static
    {
        return new static(Arr::undot($this->items));
    }

    /**
     * Returns a new collection with each item transformed by the callback.
     *
     * @param  callable $callback
     *
     * @return static
     */
    public function map(callable $callback): static
    {
        return new static(Arr::map($this->items, $callback));
    }

    /**
     * Maps each item to a collection or array and flattens by one level.
     *
     * @param  callable $callback
     *
     * @return static
     */
    public function flatMap(callable $callback): static
    {
        return $this->map($callback)->flatten(1);
    }

    /**
     * Returns a new collection containing only the items for which the
     * callback returns true.
     *
     * @param  callable|null $callback
     *
     * @return static
     */
    public function filter(?callable $callback = null): static
    {
        return new static(
            $callback
                ? array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH)
                : array_filter($this->items)
        );
    }

    /**
     * Returns a new collection containing only the items for which the
     * callback returns false (inverse of filter).
     *
     * @param  callable $callback
     *
     * @return static
     */
    public function reject(callable $callback): static
    {
        return $this->filter(fn($v, $k) => !$callback($v, $k));
    }

    /**
     * Reduces the collection to a single value using a callback.
     *
     * @param  callable $callback
     * @param  mixed    $initial
     *
     * @return mixed
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Returns a new collection with nested arrays or collections flattened up
     * to the given depth.
     *
     * @param  int $depth
     *
     * @return static
     */
    public function flatten(int $depth = PHP_INT_MAX): static
    {
        return new static(Arr::flatten($this->items, $depth));
    }

    /**
     * Iterates over the collection, calling the callback for each item.
     * Breaks if the callback returns false.
     *
     * @param  callable $callback
     *
     * @return static
     */
    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $value) {
            if ($callback($value, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Passes the collection to the callback and returns the collection
     * unchanged.
     *
     * @param  callable $callback
     *
     * @return static
     */
    public function tap(callable $callback): static
    {
        $callback($this);

        return $this;
    }

    /**
     * Returns true when the collection contains the given value or a value
     * that passes the callback.
     *
     * @param  mixed $value
     *
     * @return bool
     */
    public function contains(mixed $value): bool
    {
        if (is_callable($value) && !is_string($value)) {
            return array_any($this->items, $value);
        }

        return in_array($value, $this->items, strict: true);
    }

    /**
     * Returns the first item that passes the callback, or the default.
     *
     * @param  callable $callback
     * @param  mixed    $default
     *
     * @return mixed
     */
    public function find(callable $callback, mixed $default = null): mixed
    {
        $key = array_find_key($this->items, $callback);

        return $key !== null ? $this->items[$key] : $default;
    }

    /**
     * Searches for a value or passes each item to a callback and returns the
     * key, or false when not found.
     *
     * @param  mixed $value
     *
     * @return string|int|false
     */
    public function search(mixed $value): string|int|false
    {
        if (is_callable($value) && !is_string($value)) {
            foreach ($this->items as $key => $item) {
                if ($value($item, $key)) {
                    return $key;
                }
            }

            return false;
        }

        return array_search($value, $this->items, strict: true);
    }

    /**
     * Returns true when all items pass the given callback.
     *
     * @param  callable $callback
     *
     * @return bool
     */
    public function every(callable $callback): bool
    {
        return array_all($this->items, $callback);
    }

    /**
     * Returns the first N items (or the last N when negative).
     *
     * @param  int $limit
     *
     * @return static
     */
    public function take(int $limit): static
    {
        return $limit < 0
            ? $this->slice($limit)
            : $this->slice(0, $limit);
    }

    /**
     * Returns all items after skipping the first N.
     *
     * @param  int $count
     *
     * @return static
     */
    public function skip(int $count): static
    {
        return $this->slice($count);
    }

    /**
     * Returns a slice of the collection, preserving keys.
     *
     * @param  int      $offset
     * @param  int|null $length
     *
     * @return static
     */
    public function slice(int $offset, ?int $length = null): static
    {
        return new static(array_slice($this->items, $offset, $length, preserve_keys: true));
    }

    /**
     * Returns a collection of collections, each containing at most $size
     * items.
     *
     * @param  int $size
     *
     * @throws InvalidArgumentException If size is not greater than zero.
     *
     * @return static
     */
    public function chunk(int $size): static
    {
        if ($size <= 0) {
            throw new InvalidArgumentException('Chunk size must be greater than zero.');
        }

        $chunks = [];

        foreach (array_chunk($this->items, $size, preserve_keys: true) as $chunk) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    /**
     * Returns a sorted copy of the collection, optionally using a comparison
     * callback.
     *
     * @param  callable|null $callback
     *
     * @return static
     */
    public function sort(?callable $callback = null): static
    {
        $items = $this->items;

        $callback ? uasort($items, $callback) : asort($items);

        return new static($items);
    }

    /**
     * Returns the collection sorted by a key name or callable.
     *
     * @param  string|callable $key
     * @param  bool            $descending
     *
     * @return static
     */
    public function sortBy(string|callable $key, bool $descending = false): static
    {
        return new static(Arr::sortBy($this->items, $key, $descending));
    }

    /**
     * Returns the collection sorted descending by a key name or callable.
     *
     * @param  string|callable $key
     *
     * @return static
     */
    public function sortByDesc(string|callable $key): static
    {
        return $this->sortBy($key, descending: true);
    }

    /**
     * Returns the collection with items in reverse order, preserving keys.
     *
     * @return static
     */
    public function reverse(): static
    {
        return new static(array_reverse($this->items, preserve_keys: true));
    }

    /**
     * Returns the sum of all items or of a value resolved by a key/callable.
     *
     * @param  string|callable|null $key
     *
     * @return int|float
     */
    public function sum(string|callable|null $key = null): int|float
    {
        $values = $key === null ? $this->items : $this->resolveValues($key);

        return array_sum($values);
    }

    /**
     * Returns the average of all items or of a value resolved by a
     * key/callable.
     *
     * @param  string|callable|null $key
     *
     * @return int|float
     */
    public function avg(string|callable|null $key = null): int|float
    {
        $count = $this->count();

        return $count > 0 ? $this->sum($key) / $count : 0;
    }

    /**
     * Returns the minimum value, or the minimum of a resolved value.
     *
     * @param  string|callable|null $key
     *
     * @return mixed
     */
    public function min(string|callable|null $key = null): mixed
    {
        $values = $key === null ? $this->items : $this->resolveValues($key);

        return empty($values) ? null : min($values);
    }

    /**
     * Returns the maximum value, or the maximum of a resolved value.
     *
     * @param  string|callable|null $key
     *
     * @return mixed
     */
    public function max(string|callable|null $key = null): mixed
    {
        $values = $key === null ? $this->items : $this->resolveValues($key);

        return empty($values) ? null : max($values);
    }

    /**
     * Returns a collection of collections grouped by a key name or callable.
     *
     * @param  string|callable $key
     *
     * @return static
     */
    public function groupBy(string|callable $key): static
    {
        $groups = Arr::groupBy($this->items, $key);

        return new static(array_map(fn($group) => new static($group), $groups));
    }

    /**
     * Returns the collection re-indexed by a key name or callable.
     *
     * @param  string|callable $key
     *
     * @return static
     */
    public function keyBy(string|callable $key): static
    {
        return new static(Arr::keyBy($this->items, $key));
    }

    /**
     * Returns a collection of values plucked by the given key, optionally
     * indexed by a second key.
     *
     * @param  string      $key
     * @param  string|null $indexBy
     *
     * @return static
     */
    public function pluck(string $key, ?string $indexBy = null): static
    {
        return new static(Arr::pluck($this->items, $key, $indexBy));
    }

    /**
     * Returns a collection with duplicate values removed.
     *
     * @param  callable|null $key  Optional callback to derive the uniqueness key.
     *
     * @return static
     */
    public function unique(?callable $key = null): static
    {
        if ($key === null) {
            return new static(array_unique($this->items));
        }

        $seen   = [];
        $result = [];

        foreach ($this->items as $k => $item) {
            $id = $key($item, $k);

            if (!in_array($id, $seen, strict: true)) {
                $seen[]    = $id;
                $result[$k] = $item;
            }
        }

        return new static($result);
    }

    /**
     * Returns a new collection with the given items merged in.
     *
     * @param  array<mixed>|self $items
     *
     * @return static
     */
    public function merge(array|self $items): static
    {
        return new static(array_merge($this->items, $items instanceof self ? $items->all() : $items));
    }

    /**
     * Returns a new collection with the given values appended.
     *
     * @param  mixed ...$values
     *
     * @return static
     */
    public function push(mixed ...$values): static
    {
        $items = $this->items;
        array_push($items, ...$values);

        return new static($items);
    }

    /**
     * Returns a new collection with the given key set to the given value.
     *
     * @param  string|int $key
     * @param  mixed      $value
     *
     * @return static
     */
    public function put(string|int $key, mixed $value): static
    {
        $items       = $this->items;
        $items[$key] = $value;

        return new static($items);
    }

    /**
     * Returns a new collection with the value prepended to the beginning.
     *
     * @param  mixed          $value
     * @param  string|int|null $key
     *
     * @return static
     */
    public function prepend(mixed $value, string|int|null $key = null): static
    {
        return new static(Arr::prepend($this->items, $value, $key));
    }

    /**
     * Returns a new collection with the given keys removed.
     *
     * @param  string|int ...$keys
     *
     * @return static
     */
    public function forget(string|int ...$keys): static
    {
        $items = $this->items;

        foreach ($keys as $key) {
            unset($items[$key]);
        }

        return new static($items);
    }

    /**
     * Returns a new collection containing only the given keys.
     *
     * @param  array<int, string|int> $keys
     *
     * @return static
     */
    public function only(array $keys): static
    {
        return new static(Arr::only($this->items, $keys));
    }

    /**
     * Returns a new collection with the given keys removed.
     *
     * @param  array<int, string|int> $keys
     *
     * @return static
     */
    public function except(array $keys): static
    {
        return new static(Arr::except($this->items, $keys));
    }

    /**
     * Returns a plain array, recursively converting nested collections.
     *
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return array_map(
            static fn($value) => $value instanceof self ? $value->toArray() : $value,
            $this->items
        );
    }

    /**
     * JSON-encodes the collection using the given flags.
     *
     * @param  int $flags
     *
     * @return string
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->jsonSerialize(), $flags);
    }

    /**
     * Returns the data to serialize when JSON-encoding this collection.
     *
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Returns an iterator over the collection's items.
     *
     * @return ArrayIterator<int|string, mixed>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Returns true when the given offset key exists.
     *
     * @param  mixed $offset
     *
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * Returns the item at the given offset key.
     *
     * @param  mixed $offset
     *
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    /**
     * Sets the item at the given offset key.
     *
     * @param  mixed $offset
     * @param  mixed $value
     *
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * Removes the item at the given offset key.
     *
     * @param  mixed $offset
     *
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * Resolves an array of scalar values from the items using a key name or
     * callable.
     *
     * @param  string|callable $key
     *
     * @return array<mixed>
     */
    private function resolveValues(string|callable $key): array
    {
        return is_callable($key)
            ? array_map($key, $this->items)
            : Arr::pluck($this->items, $key);
    }
}
