<?php

declare(strict_types=1);

namespace Zen\Http;

/**
 * Represents an incoming HTTP request, exposing its method, URI, headers,
 * query/body parameters, cookies, uploaded files, and route parameters.
 */
class Request
{
    /**
     * The uppercased HTTP method (e.g. GET, POST).
     *
     * @var string
     */
    private string $method;

    /**
     * The full request URI including query string.
     *
     * @var string
     */
    private string $uri;

    /**
     * The URL-decoded path portion of the URI (no query string).
     *
     * @var string
     */
    private string $path;

    /**
     * Parsed query-string parameters from $_GET.
     *
     * @var array<string, mixed>
     */
    private array $queryParams;

    /**
     * Form body parameters from $_POST.
     *
     * @var array<string, mixed>
     */
    private array $bodyParams;

    /**
     * Decoded JSON body when the Content-Type is application/json.
     *
     * @var array<string, mixed>
     */
    private array $jsonBody;

    /**
     * Normalised request headers keyed by lowercase header name.
     *
     * @var array<string, string>
     */
    private array $headers;

    /**
     * Raw $_SERVER superglobal values.
     *
     * @var array<string, mixed>
     */
    private array $serverParams;

    /**
     * Cookie values keyed by cookie name.
     *
     * @var array<string, string>
     */
    private array $cookies;

    /**
     * Uploaded file entries from $_FILES.
     *
     * @var array<string, mixed>
     */
    private array $files;

    /**
     * Raw request body string read from php://input.
     *
     * @var string
     */
    private string $rawBody;

    /**
     * Named parameters extracted from the matched route pattern.
     *
     * @var array<string, string>
     */
    private array $routeParams = [];

    /**
     * Constructs the request from its component parts.
     *
     * @param  array<string, mixed>  $server
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     * @param  array<string, string> $cookies
     * @param  array<string, mixed>  $files
     * @param  string                $rawBody
     *
     * @return void
     */
    public function __construct(
        array $server,
        array $query = [],
        array $body = [],
        array $cookies = [],
        array $files = [],
        string $rawBody = '',
    ) {
        $this->serverParams = $server;
        $this->method       = strtoupper($server['REQUEST_METHOD'] ?? 'GET');
        $this->uri          = $server['REQUEST_URI'] ?? '/';
        $this->path         = rawurldecode(parse_url($this->uri, PHP_URL_PATH) ?? '/');
        $this->queryParams  = $query;
        $this->bodyParams   = $body;
        $this->cookies      = $cookies;
        $this->files        = $files;
        $this->rawBody      = $rawBody;
        $this->headers      = $this->parseHeaders($server);
        $this->jsonBody     = $this->parseJsonBody();
    }

    /**
     * Creates a Request instance from the current PHP superglobals.
     *
     * @return static
     */
    public static function createFromGlobals(): static
    {
        return new static(
            $_SERVER,
            $_GET,
            $_POST,
            $_COOKIE,
            $_FILES,
            file_get_contents('php://input') ?: '',
        );
    }

    /**
     * Extracts and normalises headers from the $_SERVER array.
     *
     * @param  array<string, mixed> $server
     *
     * @return array<string, string>
     */
    private function parseHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            } elseif ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $headers[strtolower(str_replace('_', '-', $key))] = $value;
            }
        }

        return $headers;
    }

    /**
     * Attempts to JSON-decode the raw body when the content type is JSON.
     *
     * @return array<string, mixed>
     */
    private function parseJsonBody(): array
    {
        if (!str_contains($this->getContentType(), 'application/json') || $this->rawBody === '') {
            return [];
        }

        $decoded = json_decode($this->rawBody, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Returns the HTTP method in uppercase.
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Returns the full request URI including any query string.
     *
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Returns the URL-decoded path portion of the URI.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Returns the value of the Content-Type header, or an empty string.
     *
     * @return string
     */
    public function getContentType(): string
    {
        return $this->headers['content-type'] ?? '';
    }

    /**
     * Returns true when the Content-Type is application/json.
     *
     * @return bool
     */
    public function isJson(): bool
    {
        return str_contains($this->getContentType(), 'application/json');
    }

    /**
     * Returns the value of a single request header by name (case-insensitive).
     *
     * @param  string $name
     * @param  string $default
     *
     * @return string
     */
    public function getHeader(string $name, string $default = ''): string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * Returns all normalised request headers keyed by lowercase name.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Returns a single query-string parameter, or the default if absent.
     *
     * @param  string $key
     * @param  mixed  $default
     *
     * @return mixed
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    /**
     * Returns a single POST body parameter, or the default if absent.
     *
     * @param  string $key
     * @param  mixed  $default
     *
     * @return mixed
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->bodyParams[$key] ?? $default;
    }

    /**
     * Returns a value from the decoded JSON body, or the whole body when
     * no key is given.
     *
     * @param  string|null $key
     * @param  mixed       $default
     *
     * @return mixed
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->jsonBody;
        }

        return $this->jsonBody[$key] ?? $default;
    }

    /**
     * Returns a value from query, body, or JSON body — whichever is set
     * first in that priority order.
     *
     * @param  string $key
     * @param  mixed  $default
     *
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key]
            ?? $this->bodyParams[$key]
            ?? $this->jsonBody[$key]
            ?? $default;
    }

    /**
     * Returns a merged array of all input parameters from JSON body, POST
     * body, and query string.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->jsonBody, $this->bodyParams, $this->queryParams);
    }

    /**
     * Returns only the listed input keys from the merged input.
     *
     * @param  string ...$keys
     *
     * @return array<string, mixed>
     */
    public function only(string ...$keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    /**
     * Returns all input except the listed keys.
     *
     * @param  string ...$keys
     *
     * @return array<string, mixed>
     */
    public function except(string ...$keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    /**
     * Returns true when all of the given keys are present in the merged
     * input.
     *
     * @param  string ...$keys
     *
     * @return bool
     */
    public function has(string ...$keys): bool
    {
        $all = $this->all();

        foreach ($keys as $key) {
            if (!array_key_exists($key, $all)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns a cookie value by name, or the default if not set.
     *
     * @param  string $name
     * @param  string $default
     *
     * @return string
     */
    public function getCookie(string $name, string $default = ''): string
    {
        return $this->cookies[$name] ?? $default;
    }

    /**
     * Returns the uploaded file entry for the given form field, or null.
     *
     * @param  string $name
     *
     * @return array<string, mixed>|null
     */
    public function getFile(string $name): array|null
    {
        return $this->files[$name] ?? null;
    }

    /**
     * Returns a value from the $_SERVER parameters, or the default.
     *
     * @param  string $name
     * @param  mixed  $default
     *
     * @return mixed
     */
    public function getServerParam(string $name, mixed $default = null): mixed
    {
        return $this->serverParams[$name] ?? $default;
    }

    /**
     * Returns the raw request body string.
     *
     * @return string
     */
    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    /**
     * Returns true when the request method matches the given string
     * (case-insensitive).
     *
     * @param  string $method
     *
     * @return bool
     */
    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /**
     * Returns true when the request method is GET.
     *
     * @return bool
     */
    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /**
     * Returns true when the request method is POST.
     *
     * @return bool
     */
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * Returns true when the request method is PUT.
     *
     * @return bool
     */
    public function isPut(): bool
    {
        return $this->method === 'PUT';
    }

    /**
     * Returns true when the request method is PATCH.
     *
     * @return bool
     */
    public function isPatch(): bool
    {
        return $this->method === 'PATCH';
    }

    /**
     * Returns true when the request method is DELETE.
     *
     * @return bool
     */
    public function isDelete(): bool
    {
        return $this->method === 'DELETE';
    }

    /**
     * Returns true when the X-Requested-With header is XMLHttpRequest.
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return $this->getHeader('x-requested-with') === 'XMLHttpRequest';
    }

    /**
     * Returns true when the request was made over HTTPS (including
     * reverse-proxy forwarding).
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        return ($this->serverParams['HTTPS'] ?? '') === 'on'
            || ($this->serverParams['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /**
     * Returns the client IP address, preferring X-Forwarded-For.
     *
     * @return string
     */
    public function getIp(): string
    {
        return $this->serverParams['HTTP_X_FORWARDED_FOR']
            ?? $this->serverParams['REMOTE_ADDR']
            ?? '';
    }

    /**
     * Returns a single named route parameter, or the default if absent.
     *
     * @param  string $name
     * @param  mixed  $default
     *
     * @return mixed
     */
    public function getRouteParam(string $name, mixed $default = null): mixed
    {
        return $this->routeParams[$name] ?? $default;
    }

    /**
     * Returns all named route parameters extracted from the matched pattern.
     *
     * @return array<string, string>
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    /**
     * Replaces the route parameters with the values extracted by the router.
     *
     * @param  array<string, string> $params
     *
     * @return void
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }
}
