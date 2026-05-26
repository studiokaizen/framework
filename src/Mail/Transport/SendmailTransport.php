<?php

declare(strict_types=1);

namespace Zen\Mail\Transport;

use Zen\Mail\MailException;
use Zen\Mail\Message;

/**
 * Mail transport that pipes the MIME message to the local sendmail binary.
 */
class SendmailTransport implements TransportInterface
{
    /**
     * Full sendmail command including any required flags.
     *
     * @param  string $command Sendmail command, e.g. '/usr/sbin/sendmail -bs'.
     *
     * @return void
     */
    public function __construct(
        private readonly string $command = '/usr/sbin/sendmail -bs',
    )
    {
    }

    /**
     * Opens a pipe to the sendmail process and writes the rendered MIME
     * message to its stdin.
     *
     * @param  Message $message
     *
     * @throws MailException If the sendmail process cannot be opened.
     *
     * @return void
     */
    public function send(Message $message): void
    {
        $handle = popen($this->command, 'w');

        if ($handle === false) {
            throw new MailException('Could not open sendmail process.');
        }

        fwrite($handle, $message->buildMime());
        pclose($handle);
    }
}
