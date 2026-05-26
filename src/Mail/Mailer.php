<?php

declare(strict_types=1);

namespace Zen\Mail;

use Zen\Mail\Transport\TransportInterface;

/**
 * Sends email messages through a configured transport, injecting the default
 * From address when none is provided.
 */
class Mailer
{
    /**
     * Stores the transport and default from address.
     *
     * @param  TransportInterface    $transport Mail transport backend.
     * @param  array<string, string> $from      Default from address map with
     *                                          'address' and 'name' keys.
     *
     * @return void
     */
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly array              $from,
    )
    {
    }

    /**
     * Sends the message.  Accepts either a ready-built Message object or a
     * callable that receives a new Message and configures it.
     *
     * ```php
     * $mailer->send(function (Message $m) {
     *     $m->to('user@example.com')->subject('Hello')->text('Hi!');
     * });
     * ```
     *
     * @param  Message|callable $message Message instance or builder callback.
     *
     * @return void
     */
    public function send(Message|callable $message): void
    {
        if (is_callable($message)) {
            $m = new Message();
            $message($m);
            $message = $m;
        }

        $message->setFrom($this->from);

        $this->transport->send($message);
    }
}
