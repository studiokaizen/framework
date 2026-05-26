<?php

declare(strict_types=1);

namespace Zen\Http;

/**
 * Represents an outgoing HTTP response with a status code, headers, body,
 * and cookies, providing a fluent interface for mutation.
 */
class Response
{
    /**
     * Map of HTTP status codes to their standard reason phrases.
     *
     * @var array<int, string>
     */
    private static array $phrases = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        206 => 'Partial Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        409 => 'Conflict',
        410 => 'Gone',
        415 => 'Unsupported Media Type',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    /**
     * HTTP status code to send.
     *
     * @var int
     */
    private int $status;

    /**
     * Response headers keyed by lowercase header name.
     *
     * @var array<string, string>
     */
    private array $headers;

    /**
     * Response body string.
     *
     * @var string
     */
    private string $body;

    /**
     * Cookies queued to be sent with the response.
     *
     * @var array<int, Cookie>
     */
    private array $cookies = [];

    /**
     * Creates a new response with optional status, headers, and body.
     *
     * @param  int                   $status
     * @param  array<string, string> $headers
     * @param  string                $body
     *
     * @return void
     */
    public function __construct(int $status = 200, array $headers = [], string $body = '')
    {
        $this->status  = $status;
        $this->headers = $headers;
        $this->body    = $body;
    }

    /**
     * Returns the current HTTP status code.
     *
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Sets the HTTP status code and returns the response.
     *
     * @param  int $status
     *
     * @return static
     */
    public function status(int $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Returns the value of a single response header by lowercase name.
     *
     * @param  string $name
     *
     * @return string
     */
    public function getHeader(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    /**
     * Returns all response headers keyed by lowercase name.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Sets a response header and returns the response.
     *
     * @param  string $name
     * @param  string $value
     *
     * @return static
     */
    public function header(string $name, string $value): static
    {
        $this->headers[strtolower($name)] = $value;

        return $this;
    }

    /**
     * Removes a response header and returns the response.
     *
     * @param  string $name
     *
     * @return static
     */
    public function removeHeader(string $name): static
    {
        unset($this->headers[strtolower($name)]);

        return $this;
    }

    /**
     * Returns the current response body string.
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Appends content to the existing body and returns the response.
     *
     * @param  string $content
     *
     * @return static
     */
    public function write(string $content): static
    {
        $this->body .= $content;

        return $this;
    }

    /**
     * Replaces the entire body with the given string and returns the response.
     *
     * @param  string $body
     *
     * @return static
     */
    public function body(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Encodes data as JSON, sets the Content-Type header, and returns the
     * response.
     *
     * @param  mixed    $data
     * @param  int|null $status
     * @param  int      $flags
     *
     * @return static
     */
    public function json(mixed $data, ?int $status = null, int $flags = 0): static
    {
        if ($status !== null) {
            $this->status = $status;
        }

        $this->headers['content-type'] = 'application/json';
        $this->body                    = json_encode($data, $flags | JSON_THROW_ON_ERROR);

        return $this;
    }

    /**
     * Queues a cookie to be sent with the response and returns the response.
     *
     * @param  Cookie $cookie
     *
     * @return static
     */
    public function cookie(Cookie $cookie): static
    {
        $this->cookies[] = $cookie;

        return $this;
    }

    /**
     * Queues an expiry cookie to delete a previously set cookie and returns
     * the response.
     *
     * @param  string $name
     * @param  string $path
     * @param  string $domain
     *
     * @return static
     */
    public function removeCookie(string $name, string $path = '/', string $domain = ''): static
    {
        $this->cookies[] = Cookie::forget($name, $path, $domain);

        return $this;
    }

    /**
     * Sets the status and Location header for a redirect and returns the
     * response.
     *
     * @param  string $url
     * @param  int    $status
     *
     * @return static
     */
    public function redirect(string $url, int $status = 302): static
    {
        $this->status              = $status;
        $this->headers['location'] = $url;

        return $this;
    }

    /**
     * Returns true when the status code indicates an empty body (204 or 304).
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return in_array($this->status, [204, 304]);
    }

    /**
     * Sends the response status line, headers, cookies, and body to the
     * client.
     *
     * @return void
     */
    public function send(): void
    {
        if (!headers_sent()) {
            $phrase = static::$phrases[$this->status] ?? 'Unknown Status';

            header(sprintf('HTTP/1.1 %d %s', $this->status, $phrase));

            foreach ($this->headers as $name => $value) {
                $normalized = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
                header(sprintf('%s: %s', $normalized, $value));
            }

            foreach ($this->cookies as $cookie) {
                header('Set-Cookie: ' . $cookie->toHeaderString(), false);
            }
        }

        if (!$this->isEmpty()) {
            echo $this->body;
        }
    }
}
