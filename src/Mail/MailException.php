<?php

declare(strict_types=1);

namespace Zen\Mail;

use RuntimeException;

/**
 * Thrown when a mail message cannot be sent or is improperly constructed.
 */
class MailException extends RuntimeException {}
