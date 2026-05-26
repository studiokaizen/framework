<?php

declare(strict_types=1);

namespace Zen\Http\Client;

/**
 * Represents the response to an outgoing HTTP request made by HttpClient.
 */
class HttpResponse
{
    /**
     * Creates an HTTP response with the given status, body, and headers.
     *
     * @param  int                   $status
     * @param  string                $body
     * @param  array<string, string> $headers
     *
     * @return void
     */
    public function __construct(
        private readonly int    $status,
        private readonly string $body,
        private readonly array  $headers,
    )
    {
    }

    /**
     * Returns the HTTP status code.
     *
     * @return int
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * Returns the raw response body string.
     *
     * @return string
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Decodes the JSON body and returns the whole payload or a single key.
     *
     * @param  string|null $key
     *
     * @return mixed
     */
    public function json(?string $key = null): mixed
    {
        $data = json_decode($this->body, true);

        if ($key === null) {
            return $data;
        }

        return is_array($data) ? ($data[$key] ?? null) : null;
    }

    /**
     * Returns the value of a single response header by name (case-insensitive).
     *
     * @param  string $name
     *
     * @return string
     */
    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    /**
     * Returns all response headers keyed by lowercase name.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Returns true when the status code is in the 2xx range.
     *
     * @return bool
     */
    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Returns true when the status code is in the 2xx range.
     *
     * @return bool
     */
    public function successful(): bool
    {
        return $this->ok();
    }

    /**
     * Returns true when the status code is outside the 2xx range.
     *
     * @return bool
     */
    public function failed(): bool
    {
        return !$this->ok();
    }

    /**
     * Returns true when the status code is in the 3xx range.
     *
     * @return bool
     */
    public function redirect(): bool
    {
        return $this->status >= 300 && $this->status < 400;
    }

    /**
     * Returns true when the status code is in the 4xx range.
     *
     * @return bool
     */
    public function clientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    /**
     * Returns true when the status code is 5xx or higher.
     *
     * @return bool
     */
    public function serverError(): bool
    {
        return $this->status >= 500;
    }

    /**
     * Throws a RequestException when the response indicates a failure,
     * otherwise returns the response for further chaining.
     *
     * @throws RequestException If the response status indicates failure.
     *
     * @return static
     */
    public function throw(): static
    {
        if ($this->failed()) {
            throw new RequestException(
                "HTTP request failed with status {$this->status}.",
                $this->status,
                $this,
            );
        }

        return $this;
    }
}
