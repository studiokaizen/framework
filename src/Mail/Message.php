<?php

declare(strict_types=1);

namespace Zen\Mail;

/**
 * Fluent builder for constructing a MIME email message with support for
 * plain-text, HTML, attachments, and custom headers.
 */
class Message
{
    /**
     * Primary recipient list.
     *
     * @var array<int, array{address: string, name: string}>
     */
    private array $to = [];

    /**
     * Carbon-copy recipient list.
     *
     * @var array<int, array{address: string, name: string}>
     */
    private array $cc = [];

    /**
     * Blind-carbon-copy recipient list.
     *
     * @var array<int, array{address: string, name: string}>
     */
    private array $bcc = [];

    /**
     * Sender address, or null when not yet set.
     *
     * @var array{address: string, name: string}|null
     */
    private ?array $from = null;

    /**
     * Reply-To address, or null when not yet set.
     *
     * @var array{address: string, name: string}|null
     */
    private ?array $replyTo = null;

    /**
     * Email subject line.
     *
     * @var string
     */
    private string $subject = '';

    /**
     * Plain-text body alternative.
     *
     * @var string
     */
    private string $text = '';

    /**
     * HTML body alternative.
     *
     * @var string
     */
    private string $html = '';

    /**
     * File attachments keyed by index.
     *
     * @var array<int, array{path: string, name: string, mime: string}>
     */
    private array $attachments = [];

    /**
     * Extra MIME headers keyed by header name.
     *
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * Adds a primary recipient.
     *
     * @param  string $address Email address.
     * @param  string $name    Optional display name.
     *
     * @return static
     */
    public function to(string $address, string $name = ''): static
    {
        $this->to[] = ['address' => $address, 'name' => $name];

        return $this;
    }

    /**
     * Adds a carbon-copy recipient.
     *
     * @param  string $address Email address.
     * @param  string $name    Optional display name.
     *
     * @return static
     */
    public function cc(string $address, string $name = ''): static
    {
        $this->cc[] = ['address' => $address, 'name' => $name];

        return $this;
    }

    /**
     * Adds a blind-carbon-copy recipient.
     *
     * @param  string $address Email address.
     * @param  string $name    Optional display name.
     *
     * @return static
     */
    public function bcc(string $address, string $name = ''): static
    {
        $this->bcc[] = ['address' => $address, 'name' => $name];

        return $this;
    }

    /**
     * Sets the From address, overriding any previously set value.
     *
     * @param  string $address Email address.
     * @param  string $name    Optional display name.
     *
     * @return static
     */
    public function from(string $address, string $name = ''): static
    {
        $this->from = ['address' => $address, 'name' => $name];

        return $this;
    }

    /**
     * Sets the Reply-To address.
     *
     * @param  string $address Email address.
     * @param  string $name    Optional display name.
     *
     * @return static
     */
    public function replyTo(string $address, string $name = ''): static
    {
        $this->replyTo = ['address' => $address, 'name' => $name];

        return $this;
    }

    /**
     * Sets the message subject.
     *
     * @param  string $subject
     *
     * @return static
     */
    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Sets the plain-text body alternative.
     *
     * @param  string $text
     *
     * @return static
     */
    public function text(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Sets the HTML body alternative.
     *
     * @param  string $html
     *
     * @return static
     */
    public function html(string $html): static
    {
        $this->html = $html;

        return $this;
    }

    /**
     * Attaches a file, guessing the MIME type from the extension when not
     * supplied.
     *
     * @param  string $path Absolute path to the file on disk.
     * @param  string $name Filename shown in the email; defaults to basename.
     * @param  string $mime MIME type; guessed from extension when empty.
     *
     * @return static
     */
    public function attach(string $path, string $name = '', string $mime = ''): static
    {
        $this->attachments[] = [
            'path' => $path,
            'name' => $name !== '' ? $name : basename($path),
            'mime' => $mime !== '' ? $mime : $this->guessMime($path),
        ];

        return $this;
    }

    /**
     * Adds a custom MIME header to the message.
     *
     * @param  string $name  Header name, e.g. 'X-Priority'.
     * @param  string $value Header value.
     *
     * @return static
     */
    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Returns the current From address array, or null when not set.
     *
     * @return array{address: string, name: string}|null
     */
    public function getFrom(): ?array
    {
        return $this->from;
    }

    /**
     * Sets the From address only when it has not already been provided by the
     * caller — used by the Mailer to inject the default sender.
     *
     * @param  array{address: string, name: string} $from
     *
     * @return void
     */
    public function setFrom(array $from): void
    {
        $this->from ??= $from;
    }

    /**
     * Returns the primary recipient list.
     *
     * @return array<int, array{address: string, name: string}>
     */
    public function getTo(): array { return $this->to; }

    /**
     * Returns the carbon-copy recipient list.
     *
     * @return array<int, array{address: string, name: string}>
     */
    public function getCc(): array { return $this->cc; }

    /**
     * Returns the blind-carbon-copy recipient list.
     *
     * @return array<int, array{address: string, name: string}>
     */
    public function getBcc(): array { return $this->bcc; }

    /**
     * Returns the message subject.
     *
     * @return string
     */
    public function getSubject(): string { return $this->subject; }

    /**
     * Returns a deduplicated list of all recipient email addresses across To,
     * Cc, and Bcc.
     *
     * @return string[]
     */
    public function getAllRecipients(): array
    {
        $addresses = [];

        foreach (array_merge($this->to, $this->cc, $this->bcc) as $r) {
            $addresses[] = $r['address'];
        }

        return array_unique($addresses);
    }

    /**
     * Assembles and returns the complete RFC-2822/MIME message string ready
     * for transport.
     *
     * @throws MailException If From, To, or Subject are not set.
     *
     * @return string Full MIME message including headers and body.
     */
    public function buildMime(): string
    {
        if ($this->from === null) {
            throw new MailException('Message must have a From address.');
        }

        if ($this->to === []) {
            throw new MailException('Message must have at least one recipient.');
        }

        if ($this->subject === '') {
            throw new MailException('Message must have a subject.');
        }

        $id       = bin2hex(random_bytes(16));
        $boundary = 'b1_' . $id;
        $altBound = 'b2_' . $id;

        $h   = $this->buildHeaders($id, $boundary, $altBound);
        $body = $this->buildBody($boundary, $altBound);

        return implode("\r\n", $h) . "\r\n\r\n" . $body;
    }

    /**
     * Builds the header array for the message, choosing the appropriate
     * Content-Type based on the presence of HTML, text, and attachments.
     *
     * @param  string $id        Unique message ID fragment.
     * @param  string $boundary  Top-level MIME boundary.
     * @param  string $altBound  Nested alternative boundary.
     *
     * @return string[]
     */
    private function buildHeaders(string $id, string $boundary, string $altBound): array
    {
        $hasAttachments = $this->attachments !== [];
        $hasBoth        = $this->text !== '' && $this->html !== '';

        $h   = [];
        $h[] = 'MIME-Version: 1.0';
        $h[] = 'Date: ' . date('r');
        $h[] = 'Message-ID: <' . $id . '@' . (gethostname() ?: 'localhost') . '>';
        $h[] = 'From: ' . $this->formatAddress($this->from);
        $h[] = 'To: ' . implode(', ', array_map($this->formatAddress(...), $this->to));

        if ($this->cc !== []) {
            $h[] = 'Cc: ' . implode(', ', array_map($this->formatAddress(...), $this->cc));
        }

        if ($this->replyTo !== null) {
            $h[] = 'Reply-To: ' . $this->formatAddress($this->replyTo);
        }

        $h[] = 'Subject: ' . $this->encodeHeader($this->subject);
        $h[] = 'X-Mailer: ZenPHP';

        foreach ($this->headers as $name => $value) {
            $h[] = "{$name}: {$value}";
        }

        if ($hasAttachments) {
            $h[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
        } elseif ($hasBoth) {
            $h[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        } elseif ($this->html !== '') {
            $h[] = 'Content-Type: text/html; charset=UTF-8';
            $h[] = 'Content-Transfer-Encoding: quoted-printable';
        } else {
            $h[] = 'Content-Type: text/plain; charset=UTF-8';
            $h[] = 'Content-Transfer-Encoding: quoted-printable';
        }

        return $h;
    }

    /**
     * Builds the MIME body string, handling single-part, multipart/alternative,
     * and multipart/mixed layouts.
     *
     * @param  string $boundary  Top-level MIME boundary.
     * @param  string $altBound  Nested alternative boundary.
     *
     * @return string
     */
    private function buildBody(string $boundary, string $altBound): string
    {
        $hasAttachments = $this->attachments !== [];
        $hasBoth        = $this->text !== '' && $this->html !== '';

        if ($hasAttachments) {
            $body = "--{$boundary}\r\n";

            if ($hasBoth) {
                $body .= "Content-Type: multipart/alternative; boundary=\"{$altBound}\"\r\n\r\n";
                $body .= "--{$altBound}\r\n" . $this->plainPart($this->text);
                $body .= "--{$altBound}\r\n" . $this->htmlPart($this->html);
                $body .= "--{$altBound}--\r\n\r\n";
            } elseif ($this->html !== '') {
                $body .= $this->htmlPart($this->html);
            } else {
                $body .= $this->plainPart($this->text);
            }

            foreach ($this->attachments as $att) {
                $body .= "--{$boundary}\r\n" . $this->attachmentPart($att);
            }

            return $body . "--{$boundary}--";
        }

        if ($hasBoth) {
            return "--{$boundary}\r\n"
                . $this->plainPart($this->text)
                . "--{$boundary}\r\n"
                . $this->htmlPart($this->html)
                . "--{$boundary}--";
        }

        if ($this->html !== '') {
            return quoted_printable_encode($this->html);
        }

        return quoted_printable_encode($this->text);
    }

    /**
     * Returns a text/plain MIME part string.
     *
     * @param  string $text Plain-text content.
     *
     * @return string
     */
    private function plainPart(string $text): string
    {
        return "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode($text) . "\r\n\r\n";
    }

    /**
     * Returns a text/html MIME part string.
     *
     * @param  string $html HTML content.
     *
     * @return string
     */
    private function htmlPart(string $html): string
    {
        return "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode($html) . "\r\n\r\n";
    }

    /**
     * Returns a base64-encoded MIME attachment part string.
     *
     * @param  array{path: string, name: string, mime: string} $att
     *
     * @return string
     */
    private function attachmentPart(array $att): string
    {
        $data = base64_encode((string) file_get_contents($att['path']));
        $name = $this->encodeHeader($att['name']);

        return "Content-Type: {$att['mime']}; name=\"{$name}\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "Content-Disposition: attachment; filename=\"{$name}\"\r\n\r\n"
            . chunk_split($data) . "\r\n";
    }

    /**
     * Formats an address array as an RFC-2822 mailbox string.
     *
     * @param  array{address: string, name: string} $addr
     *
     * @return string E.g. '"John Doe" <john@example.com>' or 'john@example.com'.
     */
    private function formatAddress(array $addr): string
    {
        if ($addr['name'] === '') {
            return $addr['address'];
        }

        return $this->encodeHeader($addr['name']) . ' <' . $addr['address'] . '>';
    }

    /**
     * Returns the value unchanged when it is pure ASCII, otherwise encodes it
     * as a base64 UTF-8 encoded-word for use in MIME headers.
     *
     * @param  string $value
     *
     * @return string
     */
    private function encodeHeader(string $value): string
    {
        if (!preg_match('/[^\x20-\x7E]/', $value)) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    /**
     * Guesses the MIME type for a file based on its extension.
     *
     * @param  string $path File path or name with an extension.
     *
     * @return string MIME type string, defaults to 'application/octet-stream'.
     */
    private function guessMime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf'        => 'application/pdf',
            'png'        => 'image/png',
            'jpg','jpeg' => 'image/jpeg',
            'gif'        => 'image/gif',
            'txt'        => 'text/plain',
            'csv'        => 'text/csv',
            'zip'        => 'application/zip',
            'docx'       => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx'       => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default      => 'application/octet-stream',
        };
    }
}
