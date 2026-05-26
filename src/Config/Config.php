<?php

declare(strict_types=1);

namespace Zen\Config;

use Zen\Support\Arr;

/**
 * Provides dot-notation access to a nested configuration array.
 */
class Config
{
    /**
     * Creates the config store with an optional initial items array.
     *
     * @param  array<string, mixed> $items
     *
     * @return void
     */
    public function __construct(private array $items = [])
    {
    }

    /**
     * Returns the value at the given dot-notation key, or the default.
     *
     * @param  string $key
     * @param  mixed  $default
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->items, $key, $default);
    }

    /**
     * Returns true when the given dot-notation key exists in the config.
     *
     * @param  string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return Arr::has($this->items, $key);
    }

    /**
     * Sets a value at the given dot-notation key.
     *
     * @param  string $key
     * @param  mixed  $value
     *
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        Arr::set($this->items, $key, $value);
    }

    /**
     * Returns the entire configuration array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }
}
