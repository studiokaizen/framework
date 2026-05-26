<?php

declare(strict_types=1);

namespace Zen\Auth;

use Zen\Database\Connection;

/**
 * Creates, validates, and revokes personal access tokens stored in the
 * database.
 */
class TokenManager
{
    /**
     * Database table that holds token records.
     */
    private const TABLE = 'personal_access_tokens';

    /**
     * Stores the database connection used for all token operations.
     *
     * @param  Connection $db
     *
     * @return void
     */
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Generates a cryptographically secure plain token, stores its SHA-256
     * hash in the database, and returns the plain value to the caller.
     *
     * @param  int|string   $userId    ID of the owning user.
     * @param  string       $name      Human-readable label for the token.
     * @param  string[]     $abilities List of ability strings; '*' grants all.
     * @param  int|null     $expiresAt Unix timestamp of expiry, or null for
     *                                 no expiry.
     *
     * @return string The plain (unhashed) token — store this securely.
     */
    public function create(
        int|string $userId,
        string     $name,
        array      $abilities = ['*'],
        ?int       $expiresAt = null,
    ): string {
        $plain = bin2hex(random_bytes(40));

        $this->db->table(self::TABLE)->insert([
            'tokenable_id'   => $userId,
            'tokenable_type' => 'users',
            'name'           => $name,
            'token'          => hash('sha256', $plain),
            'abilities'      => json_encode($abilities),
            'expires_at'     => $expiresAt,
            'created_at'     => time(),
        ]);

        return $plain;
    }

    /**
     * Looks up a plain token by hashing it and querying the database, returning
     * null if the token does not exist or has expired.
     *
     * @param  string $plainToken The plain token as supplied by the client.
     *
     * @return object|null The token record, or null if invalid or expired.
     */
    public function find(string $plainToken): ?object
    {
        $record = $this->db->table(self::TABLE)
            ->where('token', hash('sha256', $plainToken))
            ->first();

        if ($record === null) {
            return null;
        }

        if ($record->expires_at !== null && (int) $record->expires_at < time()) {
            return null;
        }

        return $record;
    }

    /**
     * Returns true if the token record grants the given ability, either
     * directly or via the wildcard '*' entry.
     *
     * @param  object $token   Token record returned by find().
     * @param  string $ability Ability string to check, e.g. 'posts:write'.
     *
     * @return bool
     */
    public function can(object $token, string $ability): bool
    {
        $abilities = json_decode($token->abilities ?? '["*"]', true) ?? ['*'];

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    /**
     * Updates the last_used_at timestamp for a token to the current time.
     *
     * @param  int $id Primary key of the token record.
     *
     * @return void
     */
    public function touch(int $id): void
    {
        $this->db->table(self::TABLE)->where('id', $id)->update(['last_used_at' => time()]);
    }

    /**
     * Permanently deletes a single token record.
     *
     * @param  int $id Primary key of the token record.
     *
     * @return void
     */
    public function revoke(int $id): void
    {
        $this->db->table(self::TABLE)->where('id', $id)->delete();
    }

    /**
     * Deletes all token records belonging to the given user.
     *
     * @param  int|string $userId Primary key of the owning user.
     *
     * @return void
     */
    public function revokeAll(int|string $userId): void
    {
        $this->db->table(self::TABLE)->where('tokenable_id', $userId)->delete();
    }

    /**
     * Removes all expired tokens from the database and returns the number of
     * rows deleted.
     *
     * @return int Number of pruned token records.
     */
    public function prune(): int
    {
        return $this->db->table(self::TABLE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', time())
            ->delete();
    }
}
