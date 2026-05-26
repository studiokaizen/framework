<?php

declare(strict_types=1);

namespace Zen\Support;

/**
 * A collection of static multibyte-safe string helpers covering searching,
 * transformation, formatting, and random generation.
 */
final class Str
{
    /**
     * Cache of previously computed StudlyCase strings.
     *
     * @var array<string, string>
     */
    private static array $studlyCache = [];

    /**
     * Cache of previously computed camelCase strings.
     *
     * @var array<string, string>
     */
    private static array $camelCache = [];

    /**
     * Cache of previously computed snake_case strings.
     *
     * @var array<string, string>
     */
    private static array $snakeCache = [];

    /**
     * Returns true when the haystack contains any of the needles.
     *
     * @param  string            $haystack
     * @param  string|array<int, string> $needles
     * @param  bool              $ignoreCase
     *
     * @return bool
     */
    public static function contains(string $haystack, string|array $needles, bool $ignoreCase = false): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle === '') {
                continue;
            }

            $check = $ignoreCase
                ? mb_stripos($haystack, $needle) !== false
                : str_contains($haystack, $needle);

            if ($check) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true when the haystack starts with any of the needles.
     *
     * @param  string            $haystack
     * @param  string|array<int, string> $needles
     *
     * @return bool
     */
    public static function startsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true when the haystack ends with any of the needles.
     *
     * @param  string            $haystack
     * @param  string|array<int, string> $needles
     *
     * @return bool
     */
    public static function endsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true when the string is empty or contains only whitespace.
     *
     * @param  string $value
     *
     * @return bool
     */
    public static function isEmpty(string $value): bool
    {
        return trim($value) === '';
    }

    /**
     * Returns true when the string is valid JSON.
     *
     * @param  string $value
     *
     * @return bool
     */
    public static function isJson(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Returns true when the string is a valid RFC 4122 UUID.
     *
     * @param  string $value
     *
     * @return bool
     */
    public static function isUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value
        );
    }

    /**
     * Returns the portion of the subject before the first occurrence of the
     * search string, or the full subject if not found.
     *
     * @param  string $subject
     * @param  string $search
     *
     * @return string
     */
    public static function before(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = mb_strpos($subject, $search);

        return $pos === false ? $subject : mb_substr($subject, 0, $pos);
    }

    /**
     * Returns the portion of the subject after the first occurrence of the
     * search string, or the full subject if not found.
     *
     * @param  string $subject
     * @param  string $search
     *
     * @return string
     */
    public static function after(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = mb_strpos($subject, $search);

        return $pos === false ? $subject : mb_substr($subject, $pos + mb_strlen($search));
    }

    /**
     * Returns the portion of the subject between two delimiters.
     *
     * @param  string $subject
     * @param  string $from
     * @param  string $to
     *
     * @return string
     */
    public static function between(string $subject, string $from, string $to): string
    {
        if ($from === '' || $to === '') {
            return $subject;
        }

        return static::before(static::after($subject, $from), $to);
    }

    /**
     * Runs a regex on the subject and returns the first capture group or full
     * match.
     *
     * @param  string $pattern
     * @param  string $subject
     *
     * @return string
     */
    public static function match(string $pattern, string $subject): string
    {
        preg_match($pattern, $subject, $matches);

        return $matches[1] ?? $matches[0] ?? '';
    }

    /**
     * Runs a regex on the subject and returns all capture group matches.
     *
     * @param  string $pattern
     * @param  string $subject
     *
     * @return array<int, string>
     */
    public static function matchAll(string $pattern, string $subject): array
    {
        preg_match_all($pattern, $subject, $matches);

        return $matches[1] ?? $matches[0] ?? [];
    }

    /**
     * Converts a string to StudlyCase, caching the result.
     *
     * @param  string $value
     *
     * @return string
     */
    public static function studly(string $value): string
    {
        if (isset(static::$studlyCache[$value])) {
            return static::$studlyCache[$value];
        }

        return static::$studlyCache[$value] = str_replace(
            ' ',
            '',
            ucwords(str_replace(['-', '_'], ' ', $value))
        );
    }

    /**
     * Converts a string to camelCase, caching the result.
     *
     * @param  string $value
     *
     * @return string
     */
    public static function camel(string $value): string
    {
        if (isset(static::$camelCache[$value])) {
            return static::$camelCache[$value];
        }

        return static::$camelCache[$value] = lcfirst(static::studly($value));
    }

    /**
     * Converts a string to snake_case using the given delimiter, caching the
     * result.
     *
     * @param  string $value
     * @param  string $delimiter
     *
     * @return string
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        $cacheKey = $value.$delimiter;

        if (isset(static::$snakeCache[$cacheKey])) {
            return static::$snakeCache[$cacheKey];
        }

        $result = preg_replace('/\s+/u', '', ucwords($value));
        $result = mb_strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1'.$delimiter, $result));

        return static::$snakeCache[$cacheKey] = $result;
    }

    /**
     * Converts a string to kebab-case.
     *
     * @param  string $value
     *
     * @return string
     */
    public static function kebab(string $value): string
    {
        return static::snake($value, '-');
    }

    /**
     * Converts a string to Title Case using multibyte functions.
     *
     * @param  string $value
     *
     * @return string
     */
    public static function title(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Converts a string to uppercase.
     *
     * @param  string $value
     *
     * @return string
     */
    public static function upper(string $value): string
    {
        return mb_strtoupper($value, 'UTF-8');
    }

    /**
     * Converts a string to lowercase.
     *
     * @param  string $value
     *
     * @return string
     */
    public static function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * Uppercases the first character of a multibyte string.
     *
     * @param  string $value
     *
     * @return string
     */
    public static function ucfirst(string $value): string
    {
        return mb_strtoupper(mb_substr($value, 0, 1)) . mb_substr($value, 1);
    }

    /**
     * Limits the string to the given display-width in characters, appending
     * the end string when truncated.
     *
     * @param  string $value
     * @param  int    $limit
     * @param  string $end
     *
     * @return string
     */
    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return mb_strimwidth($value, 0, $limit, $end, 'UTF-8');
    }

    /**
     * Limits the string to the given number of words, appending the end
     * string when truncated.
     *
     * @param  string $value
     * @param  int    $words
     * @param  string $end
     *
     * @return string
     */
    public static function words(string $value, int $words = 100, string $end = '...'): string
    {
        preg_match('/^\s*+(?:\S++\s*+){1,'.$words.'}/u', $value, $matches);

        if (!isset($matches[0]) || mb_strlen($value) === mb_strlen($matches[0])) {
            return $value;
        }

        return rtrim($matches[0]).$end;
    }

    /**
     * Returns a multibyte substring.
     *
     * @param  string   $string
     * @param  int      $start
     * @param  int|null $length
     *
     * @return string
     */
    public static function substr(string $string, int $start, ?int $length = null): string
    {
        return mb_substr($string, $start, $length, 'UTF-8');
    }

    /**
     * Replaces all occurrences of search with replace in the subject.
     *
     * @param  string|array<int, string> $search
     * @param  string|array<int, string> $replace
     * @param  string                    $subject
     *
     * @return string
     */
    public static function replace(string|array $search, string|array $replace, string $subject): string
    {
        return str_replace($search, $replace, $subject);
    }

    /**
     * Replaces the first occurrence of the search string in the subject.
     *
     * @param  string $search
     * @param  string $replace
     * @param  string $subject
     *
     * @return string
     */
    public static function replaceFirst(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strpos($subject, $search);

        return $pos === false
            ? $subject
            : substr_replace($subject, $replace, $pos, strlen($search));
    }

    /**
     * Replaces the last occurrence of the search string in the subject.
     *
     * @param  string $search
     * @param  string $replace
     * @param  string $subject
     *
     * @return string
     */
    public static function replaceLast(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strrpos($subject, $search);

        return $pos === false
            ? $subject
            : substr_replace($subject, $replace, $pos, strlen($search));
    }

    /**
     * Pads a string to the given length using the pad string and type.
     *
     * @param  string $value
     * @param  int    $length
     * @param  string $pad
     * @param  int    $type   STR_PAD_RIGHT, STR_PAD_LEFT, or STR_PAD_BOTH.
     *
     * @return string
     */
    public static function pad(string $value, int $length, string $pad = ' ', int $type = STR_PAD_RIGHT): string
    {
        return str_pad($value, $length, $pad, $type);
    }

    /**
     * Repeats the string the given number of times.
     *
     * @param  string $string
     * @param  int    $times
     *
     * @return string
     */
    public static function repeat(string $string, int $times): string
    {
        return str_repeat($string, $times);
    }

    /**
     * Wraps the value between the before and after strings.
     *
     * @param  string      $value
     * @param  string      $before
     * @param  string|null $after  Defaults to $before when null.
     *
     * @return string
     */
    public static function wrap(string $value, string $before, ?string $after = null): string
    {
        return $before.$value.($after ?? $before);
    }

    /**
     * Trims whitespace or custom characters from both ends of the string.
     *
     * @param  string      $value
     * @param  string|null $characters
     *
     * @return string
     */
    public static function trim(string $value, ?string $characters = null): string
    {
        return $characters === null ? trim($value) : trim($value, $characters);
    }

    /**
     * Trims whitespace or custom characters from the left end of the string.
     *
     * @param  string      $value
     * @param  string|null $characters
     *
     * @return string
     */
    public static function ltrim(string $value, ?string $characters = null): string
    {
        return $characters === null ? ltrim($value) : ltrim($value, $characters);
    }

    /**
     * Trims whitespace or custom characters from the right end of the string.
     *
     * @param  string      $value
     * @param  string|null $characters
     *
     * @return string
     */
    public static function rtrim(string $value, ?string $characters = null): string
    {
        return $characters === null ? rtrim($value) : rtrim($value, $characters);
    }

    /**
     * Generates a URL-friendly slug from the given title using Unicode-aware
     * rules.
     *
     * @param  string $title
     * @param  string $separator
     *
     * @return string
     */
    public static function slug(string $title, string $separator = '-'): string
    {
        $title = mb_strtolower($title, 'UTF-8');
        $title = preg_replace('/[^\p{L}\p{N}\s]/u', '', $title);
        $title = preg_replace('/\s+/u', $separator, trim($title));

        return $title;
    }

    /**
     * Returns the multibyte length of the string.
     *
     * @param  string $value
     * @param  string $encoding
     *
     * @return int
     */
    public static function length(string $value, string $encoding = 'UTF-8'): int
    {
        return mb_strlen($value, $encoding);
    }

    /**
     * Generates a cryptographically random URL-safe string of the given
     * length.
     *
     * @param  int $length
     *
     * @return string
     */
    public static function random(int $length = 16): string
    {
        $result = '';

        while (($len = strlen($result)) < $length) {
            $size   = $length - $len;
            $bytes  = random_bytes($size);
            $result .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }

        return $result;
    }

    /**
     * Generates a random version-4 UUID string.
     *
     * @return string
     */
    public static function uuid(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
