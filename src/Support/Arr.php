<?php

declare(strict_types=1);

namespace Zen\Support;

use InvalidArgumentException;

/**
 * A collection of static helpers for working with PHP arrays, including
 * dot-notation access, transformation, and aggregation utilities.
 */
final class Arr
{
    /**
     * Returns the value at the given dot-notation key, or the default.
     *
     * @param  array<string, mixed> $array
     * @param  string               $key
     * @param  mixed                $default
     *
     * @return mixed
     */
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (!str_contains($key, '.')) {
            return $default;
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Sets a value at the given dot-notation key, creating nested arrays as
     * needed.
     *
     * @param  array<string, mixed> $array
     * @param  string               $key
     * @param  mixed                $value
     *
     * @return void
     */
    public static function set(array &$array, string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $current  = &$array;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }

    /**
     * Returns true when all the given dot-notation keys exist in the array.
     *
     * @param  array<string, mixed> $array
     * @param  string|array<int, string> $key
     *
     * @return bool
     */
    public static function has(array $array, string|array $key): bool
    {
        foreach ((array) $key as $k) {
            $current = $array;

            if (array_key_exists($k, $current)) {
                continue;
            }

            foreach (explode('.', $k) as $segment) {
                if (!is_array($current) || !array_key_exists($segment, $current)) {
                    return false;
                }
                $current = $current[$segment];
            }
        }

        return true;
    }

    /**
     * Removes one or more dot-notation keys from the array.
     *
     * @param  array<string, mixed>      $array
     * @param  string|array<int, string> $key
     *
     * @return void
     */
    public static function forget(array &$array, string|array $key): void
    {
        foreach ((array) $key as $k) {
            $segments = explode('.', $k);
            $current  = &$array;

            while (count($segments) > 1) {
                $segment = array_shift($segments);

                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    continue 2;
                }

                $current = &$current[$segment];
            }

            unset($current[array_shift($segments)]);
        }
    }

    /**
     * Flattens a nested array into a dot-keyed flat array.
     *
     * @param  array<string, mixed> $array
     * @param  string               $prepend
     *
     * @return array<string, mixed>
     */
    public static function dot(array $array, string $prepend = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $results = array_merge($results, static::dot($value, $prepend.$key.'.'));
            } else {
                $results[$prepend.$key] = $value;
            }
        }

        return $results;
    }

    /**
     * Expands a dot-keyed flat array back into a nested array.
     *
     * @param  array<string, mixed> $array
     *
     * @return array<string, mixed>
     */
    public static function undot(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            static::set($result, (string) $key, $value);
        }

        return $result;
    }

    /**
     * Recursively flattens a nested array up to the given depth.
     *
     * @param  array<mixed> $array
     * @param  int          $depth
     *
     * @return array<int, mixed>
     */
    public static function flatten(array $array, int $depth = PHP_INT_MAX): array
    {
        $result = [];

        foreach ($array as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } elseif ($depth === 1) {
                array_push($result, ...array_values($item));
            } else {
                array_push($result, ...static::flatten($item, $depth - 1));
            }
        }

        return $result;
    }

    /**
     * Returns only the entries whose keys are in the given list.
     *
     * @param  array<string, mixed> $array
     * @param  array<int, string>   $keys
     *
     * @return array<string, mixed>
     */
    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * Returns the array with the given keys removed.
     *
     * @param  array<string, mixed> $array
     * @param  array<int, string>   $keys
     *
     * @return array<string, mixed>
     */
    public static function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }

    /**
     * Filters the array using a callback that receives both value and key.
     *
     * @param  array<string, mixed> $array
     * @param  callable             $callback
     *
     * @return array<string, mixed>
     */
    public static function where(array $array, callable $callback): array
    {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Returns the array with null values removed.
     *
     * @param  array<string, mixed> $array
     *
     * @return array<string, mixed>
     */
    public static function whereNotNull(array $array): array
    {
        return array_filter($array, static fn(mixed $v): bool => $v !== null);
    }

    /**
     * Returns the first element that passes the optional callback, or the
     * default.
     *
     * @param  array<mixed> $array
     * @param  callable|null $callback
     * @param  mixed         $default
     *
     * @return mixed
     */
    public static function first(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($array) ? $default : reset($array);
        }

        $key = array_find_key($array, $callback);

        return $key !== null ? $array[$key] : $default;
    }

    /**
     * Returns the last element that passes the optional callback, or the
     * default.
     *
     * @param  array<mixed>  $array
     * @param  callable|null $callback
     * @param  mixed         $default
     *
     * @return mixed
     */
    public static function last(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($array) ? $default : end($array);
        }

        return static::first(array_reverse($array, true), $callback, $default);
    }

    /**
     * Extracts a list of values from an array of items using a dot-notation
     * key, optionally indexing by a second key.
     *
     * @param  array<mixed>  $array
     * @param  string        $key
     * @param  string|null   $indexBy
     *
     * @return array<mixed>
     */
    public static function pluck(array $array, string $key, ?string $indexBy = null): array
    {
        $result = [];

        foreach ($array as $item) {
            $value = is_array($item) ? static::get($item, $key) : null;

            if ($indexBy !== null) {
                $index          = is_array($item) ? static::get($item, $indexBy) : null;
                $result[$index] = $value;
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * Maps over the array while preserving string keys.
     *
     * @param  array<string, mixed> $array
     * @param  callable             $callback
     *
     * @return array<string, mixed>
     */
    public static function map(array $array, callable $callback): array
    {
        $keys   = array_keys($array);
        $values = array_map($callback, $array, $keys);

        return array_combine($keys, $values);
    }

    /**
     * Re-indexes the array using a key name or callable.
     *
     * @param  array<mixed>        $array
     * @param  string|callable     $key
     *
     * @return array<mixed>
     */
    public static function keyBy(array $array, string|callable $key): array
    {
        $result = [];

        foreach ($array as $item) {
            $k        = is_callable($key) ? $key($item) : static::get((array) $item, $key);
            $result[$k] = $item;
        }

        return $result;
    }

    /**
     * Groups the array items by a key name or callable.
     *
     * @param  array<mixed>    $array
     * @param  string|callable $key
     *
     * @return array<string, array<int, mixed>>
     */
    public static function groupBy(array $array, string|callable $key): array
    {
        $result = [];

        foreach ($array as $item) {
            $k              = is_callable($key) ? $key($item) : static::get((array) $item, $key);
            $result[$k][]   = $item;
        }

        return $result;
    }

    /**
     * Sorts the array by a key name or callable, preserving keys.
     *
     * @param  array<mixed>    $array
     * @param  string|callable $key
     * @param  bool            $descending
     *
     * @return array<mixed>
     */
    public static function sortBy(array $array, string|callable $key, bool $descending = false): array
    {
        $scores = [];

        foreach ($array as $k => $value) {
            $scores[$k] = is_callable($key) ? $key($value, $k) : static::get((array) $value, $key);
        }

        $descending ? arsort($scores) : asort($scores);

        foreach (array_keys($scores) as $k) {
            $scores[$k] = $array[$k];
        }

        return $scores;
    }

    /**
     * Wraps the value in an array if it is not already one, or returns an
     * empty array for null.
     *
     * @param  mixed $value
     *
     * @return array<int, mixed>
     */
    public static function wrap(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    /**
     * Returns the single element of a one-item array, or the array itself.
     *
     * @param  array<mixed> $array
     *
     * @return mixed
     */
    public static function unwrap(array $array): mixed
    {
        return count($array) === 1 ? reset($array) : $array;
    }

    /**
     * Returns true when the array is associative (not a sequential list).
     *
     * @param  array<mixed> $array
     *
     * @return bool
     */
    public static function isAssoc(array $array): bool
    {
        return !array_is_list($array);
    }

    /**
     * Returns true when the array is a sequential list (0-indexed).
     *
     * @param  array<mixed> $array
     *
     * @return bool
     */
    public static function isList(array $array): bool
    {
        return array_is_list($array);
    }

    /**
     * Prepends a value to the beginning of an array, optionally with a key.
     *
     * @param  array<mixed> $array
     * @param  mixed        $value
     * @param  mixed        $key
     *
     * @return array<mixed>
     */
    public static function prepend(array $array, mixed $value, mixed $key = null): array
    {
        if ($key === null) {
            array_unshift($array, $value);
        } else {
            $array = [$key => $value] + $array;
        }

        return $array;
    }

    /**
     * Retrieves and removes a value at the given dot-notation key.
     *
     * @param  array<string, mixed> $array
     * @param  string               $key
     * @param  mixed                $default
     *
     * @return mixed
     */
    public static function pull(array &$array, string $key, mixed $default = null): mixed
    {
        $value = static::get($array, $key, $default);
        static::forget($array, $key);

        return $value;
    }

    /**
     * Returns a shuffled copy of the array.
     *
     * @param  array<mixed> $array
     *
     * @return array<int, mixed>
     */
    public static function shuffle(array $array): array
    {
        shuffle($array);

        return $array;
    }

    /**
     * Returns one or more randomly selected items from the array.
     *
     * @param  array<mixed> $array
     * @param  int          $number
     *
     * @throws InvalidArgumentException If $number exceeds the array size.
     *
     * @return mixed
     */
    public static function random(array $array, int $number = 1): mixed
    {
        $count = count($array);

        if ($number > $count) {
            throw new InvalidArgumentException(
                "Cannot pick {$number} items from an array of only {$count} item(s)."
            );
        }

        if ($number === 1) {
            return $array[array_rand($array)];
        }

        $keys = (array) array_rand($array, $number);

        return array_intersect_key($array, array_flip($keys));
    }
}
