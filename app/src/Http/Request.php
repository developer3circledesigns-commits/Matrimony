<?php
/**
 * Slim request helper. Wrap $_SERVER / $_GET / $_POST access.
 */
namespace Matrimony\Http;

final class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        return trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');
    }

    public static function segment(int $index): ?string
    {
        $segments = array_values(array_filter(explode('/', self::path())));
        return $segments[$index] ?? null;
    }

    public static function is(string $method): bool
    {
        return self::method() === strtoupper($method);
    }

    public static function input(string $key, $default = null)
    {
        $body = $_POST;
        $query = $_GET;
        if (array_key_exists($key, $body)) {
            return $body[$key];
        }
        if (array_key_exists($key, $query)) {
            return $query[$key];
        }
        return $default;
    }

    public static function query(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    public static function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }
        return null;
    }
}
