<?php

declare(strict_types=1);

namespace Zen\Http\Client;

/**
 * An immutable-style HTTP client that uses cURL to make outgoing requests and
 * returns typed response objects.
 */
class HttpClient
{
    /**
     * Base URL prepended to relative request paths.
     *
     * @var string
     */
    private string $baseUrl = '';

    /**
     * Default headers sent with every request.
     *
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * cURL operation timeout in seconds.
     *
     * @var int
     */
    private int $timeout = 30;

    /**
     * Whether to verify the server's SSL certificate.
     *
     * @var bool
     */
    private bool $verify = true;

    /**
     * Returns a clone with the given base URL set.
     *
     * @param  string $url
     *
     * @return static
     */
    public function baseUrl(string $url): static
    {
        $clone          = clone $this;
        $clone->baseUrl = rtrim($url, '/');

        return $clone;
    }

    /**
     * Returns a clone with one additional default header.
     *
     * @param  string $name
     * @param  string $value
     *
     * @return static
     */
    public function withHeader(string $name, string $value): static
    {
        $clone                   = clone $this;
        $clone->headers[$name]   = $value;

        return $clone;
    }

    /**
     * Returns a clone with the given map merged into the default headers.
     *
     * @param  array<string, string> $headers
     *
     * @return static
     */
    public function withHeaders(array $headers): static
    {
        $clone          = clone $this;
        $clone->headers = array_merge($this->headers, $headers);

        return $clone;
    }

    /**
     * Returns a clone with an Authorization header using the given token.
     *
     * @param  string $token
     * @param  string $type
     *
     * @return static
     */
    public function withToken(string $token, string $type = 'Bearer'): static
    {
        return $this->withHeader('Authorization', "{$type} {$token}");
    }

    /**
     * Returns a clone with HTTP Basic Auth credentials set.
     *
     * @param  string $username
     * @param  string $password
     *
     * @return static
     */
    public function withBasicAuth(string $username, string $password): static
    {
        return $this->withHeader('Authorization', 'Basic ' . base64_encode("{$username}:{$password}"));
    }

    /**
     * Returns a clone with the given cURL timeout in seconds.
     *
     * @param  int $seconds
     *
     * @return static
     */
    public function timeout(int $seconds): static
    {
        $clone          = clone $this;
        $clone->timeout = $seconds;

        return $clone;
    }

    /**
     * Returns a clone with SSL certificate verification disabled.
     *
     * @return static
     */
    public function withoutVerifying(): static
    {
        $clone         = clone $this;
        $clone->verify = false;

        return $clone;
    }

    /**
     * Sends a GET request with optional query parameters.
     *
     * @param  string               $url
     * @param  array<string, mixed> $query
     *
     * @throws RequestException If the cURL request fails.
     *
     * @return HttpResponse
     */
    public function get(string $url, array $query = []): HttpResponse
    {
        return $this->send('GET', $url, query: $query);
    }

    /**
     * Sends a POST request with form-encoded data.
     *
     * @param  string               $url
     * @param  array<string, mixed> $data
     *
     * @throws RequestException If the cURL request fails.
     *
     * @return HttpResponse
     */
    public function post(string $url, array $data = []): HttpResponse
    {
        return $this->send('POST', $url, form: $data);
    }

    /**
     * Sends a POST request with a JSON-encoded body.
     *
     * @param  string               $url
     * @param  array<string, mixed> $data
     *
     * @throws RequestException If the cURL request fails.
     *
     * @return HttpResponse
     */
    public function postJson(string $url, array $data = []): HttpResponse
    {
        return $this->send('POST', $url, json: $data);
    }

    /**
     * Sends a PUT request with form-encoded data.
     *
     * @param  string               $url
     * @param  array<string, mixed> $data
     *
     * @throws RequestException If the cURL request fails.
     *
     * @return HttpResponse
     */
    public function put(string $url, array $data = []): HttpResponse
    {
        return $this->send('PUT', $url, form: $data);
    }

    /**
     * Sends a PUT request with a JSON-encoded body.
     *
     * @param  string               $url
     * @param  array<string, mixed> $data
     *
     * @throws RequestException If the cURL request fails.
     *
     * @return HttpResponse
     */
    public function putJson(string $url, array $data = []): HttpResponse
    {
        return $this->send('PUT', $url, json: $data);
    }

    /**
     * Sends a PATCH request with form-encoded data.
     *
     * @param  string               $url
     * @param  array<string, mixed> $data
     *
     * @throws RequestException If the cURL request fails.
     *
     * @return HttpResponse
     */
    public function patch(string $url, array $data = []): HttpResponse
    {
        return $this->send('PATCH', $url, form: $data);
    }

    /**
     * Sends a PATCH request with a JSON-encoded body.
     *
     * @param  string               $url
     * @param  array<string, mixed> $data
     *
     * @throws RequestException If the cURL request fails.
     *
     * @return HttpResponse
     */
    public function patchJson(string $url, array $data = []): HttpResponse
    {
        return $this->send('PATCH', $url, json: $data);
    }

    /**
     * Sends a DELETE request.
     *
     * @param  string $url
     *
     * @throws RequestException If the cURL request fails.
     *
     * @return HttpResponse
     */
    public function delete(string $url): HttpResponse
    {
        return $this->send('DELETE', $url);
    }

    /**
     * Sends an HTTP request using cURL and returns a typed response.
     *
     * @param  string               $method
     * @param  string               $url
     * @param  array<string, mixed> $query
     * @param  array<string, mixed> $form
     * @param  array<string, mixed>|null $json
     * @param  array<string, mixed> $extra   Extra cURL options such as additional headers.
     *
     * @throws RequestException If cURL returns an error.
     *
     * @return HttpResponse
     */
    public function send(
        string $method,
        string $url,
        array  $query = [],
        array  $form  = [],
        ?array $json  = null,
        array  $extra = [],
    ): HttpResponse {
        $fullUrl = $this->buildUrl($url, $query);
        $headers = array_merge($this->headers, $extra['headers'] ?? []);
        $body    = null;

        if ($json !== null) {
            $body                         = json_encode($json);
            $headers['Content-Type']      = 'application/json';
            $headers['Content-Length']    = (string) strlen($body);
        } elseif ($form !== []) {
            $body = http_build_query($form);
        }

        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = "{$name}: {$value}";
        }

        $responseHeaders = [];

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verify,
            CURLOPT_SSL_VERIFYHOST => $this->verify ? 2 : 0,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_HEADERFUNCTION => static function ($ch, $header) use (&$responseHeaders): int {
                $parts = explode(':', $header, 2);

                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($header);
            },
        ]);

        $method = strtoupper($method);

        match ($method) {
            'GET'    => curl_setopt($ch, CURLOPT_HTTPGET, true),
            'POST'   => curl_setopt($ch, CURLOPT_POST, true),
            default  => curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method),
        };

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw    = curl_exec($ch);
        $error  = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($raw === false) {
            throw new RequestException("cURL error: {$error}");
        }

        return new HttpResponse($status, (string) $raw, $responseHeaders);
    }

    /**
     * Prepends the base URL and appends a query string to the given URL.
     *
     * @param  string               $url
     * @param  array<string, mixed> $query
     *
     * @return string
     */
    private function buildUrl(string $url, array $query): string
    {
        if ($this->baseUrl !== '' && !str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = $this->baseUrl . '/' . ltrim($url, '/');
        }

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return $url;
    }
}
