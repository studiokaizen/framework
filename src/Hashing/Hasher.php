<?php

declare(strict_types=1);

namespace Zen\Hashing;

/**
 * Wraps PHP's native password_* functions, supporting bcrypt and argon2id
 * drivers with configurable cost/round options.
 */
class Hasher
{
    /**
     * Stores the driver name and work-factor used for all hashing operations.
     *
     * @param  string $driver 'bcrypt' or 'argon2id'.
     * @param  int    $rounds Cost factor for bcrypt (ignored for argon2id).
     *
     * @return void
     */
    public function __construct(
        private readonly string $driver = 'bcrypt',
        private readonly int    $rounds = 12,
    )
    {
    }

    /**
     * Hashes a plain-text value using the configured driver and options.
     *
     * @param  string               $value   Plain-text string to hash.
     * @param  array<string, mixed> $options Driver-specific overrides, e.g.
     *                                       ['rounds' => 14] for bcrypt.
     *
     * @return string The resulting password hash.
     */
    public function make(string $value, array $options = []): string
    {
        return password_hash($value, $this->algo(), $this->options($options));
    }

    /**
     * Verifies a plain-text value against a stored hash.
     *
     * @param  string $value Plain-text candidate.
     * @param  string $hash  Stored hash to compare against.
     *
     * @return bool True if the value matches the hash.
     */
    public function verify(string $value, string $hash): bool
    {
        return password_verify($value, $hash);
    }

    /**
     * Returns true when a stored hash was produced with different options and
     * should be rehashed on next successful login.
     *
     * @param  string               $hash    Existing stored hash.
     * @param  array<string, mixed> $options Options to compare against.
     *
     * @return bool
     */
    public function needsRehash(string $hash, array $options = []): bool
    {
        return password_needs_rehash($hash, $this->algo(), $this->options($options));
    }

    /**
     * Resolves the PHP PASSWORD_* constant for the configured driver.
     *
     * @return string|int
     */
    private function algo(): string|int
    {
        return $this->driver === 'argon2id' ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    /**
     * Merges caller-supplied overrides with the default driver options.
     *
     * @param  array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function options(array $overrides): array
    {
        if ($this->driver === 'bcrypt') {
            return ['cost' => $overrides['rounds'] ?? $this->rounds];
        }

        return $overrides;
    }
}
