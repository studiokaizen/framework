<?php

declare(strict_types=1);

namespace Zen\Encryption;

use RuntimeException;

/**
 * Thrown when encryption or decryption fails, or when a payload is tampered
 * with.
 */
class EncryptionException extends RuntimeException {}
