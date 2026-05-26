<?php

declare(strict_types=1);

namespace Zen\Auth;

use Zen\Database\Connection;
use Zen\Hashing\Hasher;
use Zen\Session\Session;

/**
 * Handles session-based and token-based authentication against a user table.
 */
class AuthManager
{
    /**
     * Session key used to persist the authenticated user's ID.
     */
    private const SESSION_KEY = '_auth_id';

    /**
     * Cached user record for the session-authenticated user.
     *
     * @var array<string, mixed>|null
     */
    private ?array $resolvedUser = null;

    /**
     * User record resolved from a Bearer token.
     *
     * @var array<string, mixed>|null
     */
    private ?array $tokenUser = null;

    /**
     * Raw token database record for the current token-authenticated user.
     *
     * @var object|null
     */
    private ?object $tokenRecord = null;

    /**
     * Initialises the manager with the database, hasher, session, and
     * optional table / column configuration.
     *
     * @param  Connection $db            Database connection.
     * @param  Hasher     $hasher        Password hasher.
     * @param  Session    $session       Session store.
     * @param  string     $table         User table name.
     * @param  string     $usernameField Column used as the login identifier.
     * @param  string     $passwordField Column that holds the password hash.
     *
     * @return void
     */
    public function __construct(
        private readonly Connection $db,
        private readonly Hasher     $hasher,
        private readonly Session    $session,
        private readonly string     $table         = 'users',
        private readonly string     $usernameField = 'email',
        private readonly string     $passwordField = 'password',
    )
    {
    }

    /**
     * Validates credentials and, on success, logs the user in via the session.
     * Automatically rehashes the password if the hashing options have changed.
     *
     * @param  string $username Value to match against the username field.
     * @param  string $password Plain-text password to verify.
     *
     * @return bool True on successful authentication, false otherwise.
     */
    public function attempt(string $username, string $password): bool
    {
        $user = $this->findByUsername($username);

        if ($user === null) {
            return false;
        }

        if (!$this->hasher->verify($password, $user[$this->passwordField] ?? '')) {
            return false;
        }

        $this->login($user['id']);

        if ($this->hasher->needsRehash($user[$this->passwordField])) {
            $this->db->table($this->table)
                ->where('id', $user['id'])
                ->update([$this->passwordField => $this->hasher->make($password)]);
        }

        return true;
    }

    /**
     * Stores the user ID in the session and regenerates the session ID to
     * prevent fixation attacks.
     *
     * @param  int|string $id Primary key of the user to log in.
     *
     * @return void
     */
    public function login(int|string $id): void
    {
        $this->session->start();
        $this->session->set(self::SESSION_KEY, $id);
        $this->session->regenerate();
        $this->resolvedUser = null;
    }

    /**
     * Removes the authentication session key, regenerates the session ID, and
     * clears all cached user data.
     *
     * @return void
     */
    public function logout(): void
    {
        $this->session->start();
        $this->session->forget(self::SESSION_KEY);
        $this->session->regenerate();
        $this->resolvedUser = null;
        $this->tokenUser    = null;
        $this->tokenRecord  = null;
    }

    /**
     * Populates the token-user state without touching the session, used by the
     * token authentication middleware.
     *
     * @param  array<string, mixed> $user        The resolved user record.
     * @param  object               $tokenRecord The raw token row from the database.
     *
     * @return void
     */
    public function loginViaToken(array $user, object $tokenRecord): void
    {
        $this->tokenUser    = $user;
        $this->tokenRecord  = $tokenRecord;
        $this->resolvedUser = $user;
    }

    /**
     * Returns true if a user is currently authenticated via session or token.
     *
     * @return bool
     */
    public function check(): bool
    {
        if ($this->tokenUser !== null) {
            return true;
        }

        $this->session->start();

        return $this->session->has(self::SESSION_KEY);
    }

    /**
     * Returns true when no authenticated user is present.
     *
     * @return bool
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Returns the authenticated user's primary key, or null for guests.
     *
     * @return int|string|null
     */
    public function id(): int|string|null
    {
        if ($this->tokenUser !== null) {
            return $this->tokenUser['id'] ?? null;
        }

        $this->session->start();

        return $this->session->get(self::SESSION_KEY);
    }

    /**
     * Returns the full user record for the authenticated user, fetching it
     * from the database on first call and caching the result thereafter.
     *
     * @return array<string, mixed>|null Null when no user is authenticated.
     */
    public function user(): ?array
    {
        if ($this->resolvedUser !== null) {
            return $this->resolvedUser;
        }

        if (!$this->check()) {
            return null;
        }

        $this->resolvedUser = $this->findById((int) $this->id());

        return $this->resolvedUser;
    }

    /**
     * Returns the raw token record for a token-authenticated request, or null
     * for session-authenticated and guest requests.
     *
     * @return object|null
     */
    public function token(): ?object
    {
        return $this->tokenRecord;
    }

    /**
     * Checks credentials without creating a session; useful for one-off
     * credential verification.
     *
     * @param  string $username Value to match against the username field.
     * @param  string $password Plain-text password to check.
     *
     * @return bool
     */
    public function validate(string $username, string $password): bool
    {
        $user = $this->findByUsername($username);

        return $user !== null
            && $this->hasher->verify($password, $user[$this->passwordField] ?? '');
    }

    /**
     * Fetches a user record by its numeric primary key.
     *
     * @param  int $id
     *
     * @return array<string, mixed>|null Null when no matching record is found.
     */
    public function findById(int $id): ?array
    {
        $row = $this->db->table($this->table)->where('id', $id)->first();

        return $row !== null ? (array) $row : null;
    }

    /**
     * Fetches a user record by the configured username field.
     *
     * @param  string $username
     *
     * @return array<string, mixed>|null Null when no matching record is found.
     */
    private function findByUsername(string $username): ?array
    {
        $row = $this->db->table($this->table)->where($this->usernameField, $username)->first();

        return $row !== null ? (array) $row : null;
    }
}
