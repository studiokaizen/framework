<?php

declare(strict_types=1);

namespace Zen\Mail\Transport;

use Zen\Mail\MailException;
use Zen\Mail\Message;

class SmtpTransport implements TransportInterface
{
    /**
     * @var resource|null
     */
    private $socket = null;

    public function __construct(
        private readonly string $host,
        private readonly int    $port       = 587,
        private readonly string $username   = '',
        private readonly string $password   = '',
        private readonly string $encryption = 'tls', // 'tls', 'ssl', or ''
        private readonly int    $timeout    = 30,
    )
    {
    }

    public function send(Message $message): void
    {
        try {
            $this->open();
            $this->authenticate();
            $this->sendMessage($message);
        } finally {
            $this->close();
        }
    }

    private function open(): void
    {
        $host = $this->encryption === 'ssl'
            ? 'ssl://' . $this->host
            : $this->host;

        $this->socket = @stream_socket_client(
            "{$host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
        );

        if ($this->socket === false) {
            throw new MailException("SMTP connection failed ({$errno}): {$errstr}");
        }

        stream_set_timeout($this->socket, $this->timeout);

        $this->expect(220);

        $domain = gethostname() ?: 'localhost';
        $this->command("EHLO {$domain}");
        $this->expect(250);

        if ($this->encryption === 'tls') {
            $this->command('STARTTLS');
            $this->expect(220);

            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new MailException('Failed to enable STARTTLS.');
            }

            $this->command("EHLO {$domain}");
            $this->expect(250);
        }
    }

    private function authenticate(): void
    {
        if ($this->username === '' && $this->password === '') {
            return;
        }

        $this->command('AUTH LOGIN');
        $this->expect(334);
        $this->command(base64_encode($this->username));
        $this->expect(334);
        $this->command(base64_encode($this->password));
        $this->expect(235);
    }

    private function sendMessage(Message $message): void
    {
        $from = $message->getFrom();

        $this->command("MAIL FROM:<{$from['address']}>");
        $this->expect(250);

        foreach ($message->getAllRecipients() as $address) {
            $this->command("RCPT TO:<{$address}>");
            $this->expect(250);
        }

        $this->command('DATA');
        $this->expect(354);

        $mime = preg_replace('/^\./', '..', $message->buildMime(), flags: PREG_OFFSET_CAPTURE);
        fwrite($this->socket, $mime . "\r\n.\r\n");

        $this->expect(250);

        $this->command('QUIT');
        $this->expect(221);
    }

    private function command(string $cmd): void
    {
        fwrite($this->socket, $cmd . "\r\n");
    }

    private function expect(int $code): string
    {
        $response = '';

        while ($line = fgets($this->socket, 512)) {
            $response .= $line;

            // A line ending in "NNN " (space after code) is the last line of a response.
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        if ((int) substr($response, 0, 3) !== $code) {
            throw new MailException(
                "Expected SMTP {$code}, got: " . trim($response)
            );
        }

        return $response;
    }

    private function close(): void
    {
        if ($this->socket !== null) {
            fclose($this->socket);
            $this->socket = null;
        }
    }
}
