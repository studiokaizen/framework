<?php

declare(strict_types=1);

namespace Zen\Encryption;

/**
 * Encrypts and decrypts arbitrary values using AES-256-CBC with an HMAC-SHA256
 * message authentication code.
 */
class Encrypter
{
    /**
     * OpenSSL cipher identifier used for all encrypt/decrypt operations.
     */
    private const CIPHER = 'AES-256-CBC';

    /**
     * Validates that the key is exactly 32 bytes.
     *
     * @param  string $key Raw 32-byte encryption key.
     *
     * @throws EncryptionException If the key is not exactly 32 bytes.
     *
     * @return void
     */
    public function __construct(private readonly string $key)
    {
        if (strlen($key) !== 32) {
            throw new EncryptionException('The encryption key must be exactly 32 bytes.');
        }
    }

    /**
     * Generates a random 32-byte key suitable for use with this class.
     *
     * @return string Raw 32-byte binary key.
     */
    public static function generateKey(): string
    {
        return random_bytes(32);
    }

    /**
     * Serializes, encrypts, and base64-encodes a value along with a random IV
     * and an HMAC MAC.
     *
     * @param  mixed $value Any serializable PHP value.
     *
     * @throws EncryptionException If OpenSSL fails to encrypt.
     *
     * @return string Base64-encoded JSON payload containing iv, value, and mac.
     */
    public function encrypt(mixed $value): string
    {
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));

        $encrypted = openssl_encrypt(serialize($value), self::CIPHER, $this->key, 0, $iv);

        if ($encrypted === false) {
            throw new EncryptionException('Could not encrypt the data.');
        }

        $iv  = base64_encode($iv);
        $mac = $this->mac($iv, $encrypted);

        return base64_encode(json_encode(['iv' => $iv, 'value' => $encrypted, 'mac' => $mac]));
    }

    /**
     * Decodes, verifies the MAC, decrypts, and unserializes the payload
     * produced by encrypt().
     *
     * @param  string $payload Base64-encoded payload from encrypt().
     *
     * @throws EncryptionException If the MAC is invalid or decryption fails.
     *
     * @return mixed The original value passed to encrypt().
     */
    public function decrypt(string $payload): mixed
    {
        $data = $this->decode($payload);

        if (!hash_equals($this->mac($data['iv'], $data['value']), $data['mac'])) {
            throw new EncryptionException('The payload MAC is invalid.');
        }

        $decrypted = openssl_decrypt($data['value'], self::CIPHER, $this->key, 0, base64_decode($data['iv']));

        if ($decrypted === false) {
            throw new EncryptionException('Could not decrypt the data.');
        }

        return unserialize($decrypted);
    }

    /**
     * Computes an HMAC-SHA256 over the concatenated IV and ciphertext.
     *
     * @param  string $iv    Base64-encoded IV string.
     * @param  string $value Ciphertext produced by OpenSSL.
     *
     * @return string Hex-encoded HMAC digest.
     */
    private function mac(string $iv, string $value): string
    {
        return hash_hmac('sha256', $iv . $value, $this->key);
    }

    /**
     * Base64-decodes and JSON-parses a payload, validating required fields.
     *
     * @param  string $payload Raw base64 payload string.
     *
     * @throws EncryptionException If the payload is not a valid JSON object
     *                             with 'iv', 'value', and 'mac' keys.
     *
     * @return array{iv: string, value: string, mac: string}
     */
    private function decode(string $payload): array
    {
        $data = json_decode(base64_decode($payload), true);

        if (!is_array($data) || !isset($data['iv'], $data['value'], $data['mac'])) {
            throw new EncryptionException('The payload is invalid.');
        }

        return $data;
    }
}
