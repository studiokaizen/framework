<?php

declare(strict_types=1);

namespace Zen\Storage;

use InvalidArgumentException;

/**
 * Manages multiple named storage disks, resolving each one lazily and
 * forwarding method calls to the default disk via __call.
 */
class StorageManager
{
    /**
     * Cache of already-resolved Disk instances keyed by disk name.
     *
     * @var array<string, Disk>
     */
    private array $resolved = [];

    /**
     * Stores the storage configuration array.
     *
     * @param  array<string, mixed> $config Full storage configuration, expects
     *                                      'disks' and 'default' keys.
     *
     * @return void
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * Returns the Disk instance for the given name, instantiating it on first
     * call.
     *
     * @param  string $name Disk name as defined under config('storage.disks').
     *
     * @throws InvalidArgumentException If the named disk is not configured.
     *
     * @return Disk
     */
    public function disk(string $name): Disk
    {
        if (!isset($this->resolved[$name])) {
            $cfg = $this->config['disks'][$name]
                ?? throw new InvalidArgumentException("Storage disk [{$name}] is not configured.");

            $this->resolved[$name] = new Disk($cfg['root'], $cfg['url'] ?? '');
        }

        return $this->resolved[$name];
    }

    /**
     * Proxies any unknown method call to the default disk, allowing the
     * manager to be used as if it were a Disk instance.
     *
     * @param  string  $method
     * @param  mixed[] $args
     *
     * @return mixed
     */
    public function __call(string $method, array $args): mixed
    {
        return $this->disk($this->config['default'] ?? 'local')->$method(...$args);
    }
}
