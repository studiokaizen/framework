<?php

declare(strict_types=1);

namespace Zen\Mail\Transport;

use Zen\Mail\Message;

/**
 * Contract for mail transport backends.
 */
interface TransportInterface
{
    /**
     * Transmits the given message to its recipients.
     *
     * @param  Message $message Fully configured message to send.
     *
     * @return void
     */
    public function send(Message $message): void;
}
