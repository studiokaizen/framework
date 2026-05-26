<?php

declare(strict_types=1);

namespace Zen\Http;

/**
 * Represents an HTTP Set-Cookie value and serialises it to a header string.
 */
class Cookie
{
    /**
     * Creates a new cookie with the given attributes.
     *
     * @param  string $name
     * @param  string $value
     * @param  int    $expires  Unix timestamp for expiry; 0 means session.
     * @param  string $path
     * @param  string $domain
     * @param  bool   $secure
     * @param  bool   $httpOnly
     * @param  string $sameSite  'Lax', 'Strict', or 'None'.
     *
     * @return void
     */
    public function __construct(
        private readonly string $name,
        private readonly string $value,
        private readonly int    $expires  = 0,
        private readonly string $path     = '/',
        private readonly string $domain   = '',
        private readonly bool   $secure   = false,
        private readonly bool   $httpOnly = true,
        private readonly string $sameSite = 'Lax',
    )
    {
    }

    /**
     * Named constructor that creates a new cookie with the given attributes.
     *
     * @param  string $name
     * @param  string $value
     * @param  int    $expires
     * @param  string $path
     * @param  string $domain
     * @param  bool   $secure
     * @param  bool   $httpOnly
     * @param  string $sameSite
     *
     * @return static
     */
    public static function make(
        string $name,
        string $value,
        int    $expires  = 0,
        string $path     = '/',
        string $domain   = '',
        bool   $secure   = false,
        bool   $httpOnly = true,
        string $sameSite = 'Lax',
    ): static {
        return new static($name, $value, $expires, $path, $domain, $secure, $httpOnly, $sameSite);
    }

    /**
     * Creates an expired cookie that instructs the browser to delete an
     * existing cookie by the same name.
     *
     * @param  string $name
     * @param  string $path
     * @param  string $domain
     *
     * @return static
     */
    public static function forget(string $name, string $path = '/', string $domain = ''): static
    {
        return new static($name, '', time() - 3600, $path, $domain);
    }

    /**
     * Returns the cookie name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the cookie value.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Serialises the cookie to a Set-Cookie header value string.
     *
     * @return string
     */
    public function toHeaderString(): string
    {
        $header = urlencode($this->name) . '=' . urlencode($this->value);

        if ($this->expires !== 0) {
            $header .= '; Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $this->expires);
            $header .= '; Max-Age=' . max(0, $this->expires - time());
        }

        $header .= '; Path=' . $this->path;

        if ($this->domain !== '') {
            $header .= '; Domain=' . $this->domain;
        }

        if ($this->secure) {
            $header .= '; Secure';
        }

        if ($this->httpOnly) {
            $header .= '; HttpOnly';
        }

        if ($this->sameSite !== '') {
            $header .= '; SameSite=' . $this->sameSite;
        }

        return $header;
    }
}
