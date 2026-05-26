<?php

declare(strict_types=1);

namespace Zen\DependencyInjection;

use ArrayAccess;
use Closure;
use InvalidArgumentException;
use RuntimeException;
use SplObjectStorage;

/**
 * A service container that lazily resolves factory closures on first access.
 */
class Container implements ArrayAccess
{
    /**
     * Stores raw values and unresolved factory closures indexed by key.
     *
     * @var array<string, mixed>
     */
    private array $values = [];

    /**
     * Set of closures marked as protected — returned as-is instead of being
     * invoked.
     *
     * @var SplObjectStorage
     */
    private SplObjectStorage $protected;

    /**
     * Keys of services that have been resolved and can no longer be overridden.
     *
     * @var array<string, bool>
     */
    private array $frozen = [];

    /**
     * Original factory closures for services that have already been resolved.
     *
     * @var array<string, Closure>
     */
    private array $raw = [];

    /**
     * Registry of all defined keys for fast existence checks.
     *
     * @var array<string, bool>
     */
    private array $keys = [];

    /**
     * Initialises the protected closures storage.
     */
    public function __construct()
    {
        $this->protected = new SplObjectStorage();
    }

    /**
     * Returns true if a binding exists for the given key.
     *
     * @param  mixed $offset
     *
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->keys[$offset]);
    }

    /**
     * Resolves and returns the value for the given key. Factory closures are
     * invoked on first access, their result cached, and the key frozen to
     * prevent further overrides.
     *
     * @param  mixed $offset
     *
     * @throws InvalidArgumentException If the key has not been defined.
     *
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!isset($this->keys[$offset])) {
            throw new InvalidArgumentException(
                sprintf('Identifier "%s" is not defined.', $offset)
            );
        }

        if (
            isset($this->raw[$offset])
            || !($this->values[$offset] instanceof Closure)
            || $this->protected->contains($this->values[$offset])
        ) {
            return $this->values[$offset];
        }

        $raw = $this->values[$offset];
        $resolved = $raw($this);
        $this->raw[$offset] = $raw;
        $this->values[$offset] = $resolved;
        $this->frozen[$offset] = true;

        return $resolved;
    }

    /**
     * Binds a value or factory closure to the given key.
     *
     * @param  mixed $offset
     * @param  mixed $value
     *
     * @throws RuntimeException If the key has already been resolved and frozen.
     *
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (isset($this->frozen[$offset])) {
            throw new RuntimeException(
                sprintf('Cannot override frozen service "%s".', $offset)
            );
        }

        $this->values[$offset] = $value;
        $this->keys[$offset] = true;
    }

    /**
     * Removes a binding and cleans up all associated state.
     *
     * @param  mixed $offset
     *
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        if (!isset($this->keys[$offset])) {
            return;
        }

        $value = $this->values[$offset];

        if ($value instanceof Closure) {
            $this->protected->detach($value);
        }

        unset(
            $this->values[$offset],
            $this->frozen[$offset],
            $this->raw[$offset],
            $this->keys[$offset],
        );
    }

    /**
     * Marks a closure as a protected value so it is returned as-is rather than
     * invoked as a factory.
     *
     * ```php
     * $app['filter'] = $app->protect(function ($value) {
     *     return strtolower($value);
     * });
     * ```
     *
     * @param  Closure $callable
     *
     * @return Closure
     */
    public function protect(Closure $callable): Closure
    {
        $this->protected->attach($callable);

        return $callable;
    }

    /**
     * Returns the original factory closure for a key, or the raw value if it
     * was never a factory.
     *
     * @param  string $id
     *
     * @throws InvalidArgumentException If the key has not been defined.
     *
     * @return mixed
     */
    public function raw(string $id): mixed
    {
        if (!isset($this->keys[$id])) {
            throw new InvalidArgumentException(
                sprintf('Identifier "%s" is not defined.', $id)
            );
        }

        return $this->raw[$id] ?? $this->values[$id];
    }

    /**
     * Wraps an existing factory with a decorator closure. The decorator
     * receives the resolved service and the container as arguments.
     *
     * ```php
     * $app->extend('mailer', function ($mailer, $app) {
     *     $mailer->setLogger($app['logger']);
     *     return $mailer;
     * });
     * ```
     *
     * @param  string  $id
     * @param  Closure $callable
     *
     * @throws InvalidArgumentException If the key has not been defined or is not a factory.
     * @throws RuntimeException         If the key has already been resolved and frozen.
     *
     * @return Closure
     */
    public function extend(string $id, Closure $callable): Closure
    {
        if (!isset($this->keys[$id])) {
            throw new InvalidArgumentException(
                sprintf('Identifier "%s" is not defined.', $id)
            );
        }

        if (isset($this->frozen[$id])) {
            throw new RuntimeException(
                sprintf('Cannot extend frozen service "%s".', $id)
            );
        }

        $factory = $this->values[$id];

        if (!($factory instanceof Closure)) {
            throw new InvalidArgumentException(
                sprintf('Identifier "%s" does not contain an object definition.', $id)
            );
        }

        $extended = function (Container $container) use ($callable, $factory): mixed {
            return $callable($factory($container), $container);
        };

        $this[$id] = $extended;

        return $extended;
    }
}
