<?php

declare(strict_types=1);

namespace Zen\Session;

/**
 * Thin wrapper around PHP's native session functions with support for flash
 * messages and configurable cookie parameters.
 */
class Session
{
    /**
     * Session key under which flash message data is nested.
     *
     * @var string
     */
    private string $flashKey;

    /**
     * Initialises the session with optional configuration.
     *
     * @param  array<string, mixed> $config Session configuration values.
     *                                      Supported keys: name, lifetime,
     *                                      secure, httponly, samesite,
     *                                      flash_key.
     *
     * @return void
     */
    public function __construct(private readonly array $config = [])
    {
        $this->flashKey = $config['flash_key'] ?? '_flash';
    }

    /**
     * Starts the PHP session with the configured cookie parameters.
     * Idempotent — safe to call multiple times.
     *
     * @return void
     */
    public function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $name = $this->config['name'] ?? 'zen_session';

        session_name($name);

        session_set_cookie_params([
            'lifetime' => (int) ($this->config['lifetime'] ?? 120) * 60,
            'path'     => '/',
            'secure'   => (bool) ($this->config['secure']   ?? false),
            'httponly' => (bool) ($this->config['httponly']  ?? true),
            'samesite' => (string) ($this->config['samesite'] ?? 'Lax'),
        ]);

        session_start();
    }

    /**
     * Returns a session value by key, or a default when the key is absent.
     *
     * @param  string $key
     * @param  mixed  $default
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Stores a value in the session under the given key.
     *
     * @param  string $key
     * @param  mixed  $value
     *
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Returns true when the session contains the given key.
     *
     * @param  string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Removes a key from the session.
     *
     * @param  string $key
     *
     * @return void
     */
    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Sets or retrieves a flash value.  When called with only a key, the
     * stored value is returned and immediately removed.  When called with both
     * key and value, the value is stored for the next read.
     *
     * @param  string $key
     * @param  mixed  $value Pass null (or omit) to read and clear.
     *
     * @return mixed The stored value when reading, null when writing.
     */
    public function flash(string $key, mixed $value = null): mixed
    {
        if ($value !== null) {
            $_SESSION[$this->flashKey][$key] = $value;

            return null;
        }

        $stored = $_SESSION[$this->flashKey][$key] ?? null;
        unset($_SESSION[$this->flashKey][$key]);

        return $stored;
    }

    /**
     * Returns the entire $_SESSION superglobal as an array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $_SESSION ?? [];
    }

    /**
     * Regenerates the session ID, invalidating the old one.
     *
     * @return void
     */
    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /**
     * Clears the session data and destroys the session.
     *
     * @return void
     */
    public function destroy(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
