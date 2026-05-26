<?php

declare(strict_types=1);

namespace Zen\Cache;

/**
 * A file-based cache store that serialises values to disk and respects TTL
 * expiry.
 */
class Cache
{
    /**
     * Creates the cache with the storage directory path and default TTL.
     *
     * @param  string $path  Absolute path to the cache directory.
     * @param  int    $ttl   Default lifetime in seconds (0 = never expire).
     *
     * @return void
     */
    public function __construct(
        private readonly string $path,
        private readonly int    $ttl = 3600,
    )
    {
    }

    /**
     * Returns the cached value for the key, or the default when missing or
     * expired.
     *
     * @param  string $key
     * @param  mixed  $default
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->read($key)['value'];
    }

    /**
     * Stores a value under the given key with an optional TTL override.
     *
     * @param  string   $key
     * @param  mixed    $value
     * @param  int|null $ttl   Seconds until expiry; null uses the default TTL.
     *
     * @return bool
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }

        $seconds = $ttl ?? $this->ttl;
        $expires = $seconds > 0 ? time() + $seconds : null;

        return file_put_contents(
            $this->filePath($key),
            serialize(['expires' => $expires, 'value' => $value]),
            LOCK_EX,
        ) !== false;
    }

    /**
     * Returns true when a non-expired entry exists for the key.
     *
     * @param  string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        $file = $this->filePath($key);

        if (!file_exists($file)) {
            return false;
        }

        $data = $this->read($key);

        if ($data['expires'] !== null && $data['expires'] < time()) {
            $this->forget($key);

            return false;
        }

        return true;
    }

    /**
     * Deletes the cache entry for the given key.
     *
     * @param  string $key
     *
     * @return bool
     */
    public function forget(string $key): bool
    {
        $file = $this->filePath($key);

        return file_exists($file) && unlink($file);
    }

    /**
     * Deletes all cache entries from the storage directory.
     *
     * @return bool
     */
    public function flush(): bool
    {
        if (!is_dir($this->path)) {
            return true;
        }

        foreach (glob($this->path . DIRECTORY_SEPARATOR . '*.cache') ?: [] as $file) {
            unlink($file);
        }

        return true;
    }

    /**
     * Returns the cached value if present; otherwise runs the callback,
     * stores its result, and returns it.
     *
     * @param  string   $key
     * @param  int      $ttl
     * @param  callable $callback
     *
     * @return mixed
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        if ($this->has($key)) {
            return $this->read($key)['value'];
        }

        $value = $callback();

        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Reads and unserialises the cache file for the given key.
     *
     * @param  string $key
     *
     * @return array{expires: int|null, value: mixed}
     */
    private function read(string $key): array
    {
        return unserialize(file_get_contents($this->filePath($key)));
    }

    /**
     * Returns the file path used to store the given cache key.
     *
     * @param  string $key
     *
     * @return string
     */
    private function filePath(string $key): string
    {
        return $this->path . DIRECTORY_SEPARATOR . sha1($key) . '.cache';
    }
}
