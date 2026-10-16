<?php
declare(strict_types=1);

namespace Matrimony\Security;

final class Validation
{
    public static function email(string $v): ?string
    {
        $v = strtolower(trim($v));
        return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : null;
    }

    public static function phone(string $v): ?string
    {
        $digits = preg_replace('/\D+/', '', $v);
        if ($digits === '') {
            return null;
        }
        // Accept 10-digit Indian mobiles (optionally with 91 prefix) or 7-15 digit intl
        if (preg_match('/^91[6-9]\d{9}$/', $digits)) {
            return $digits;
        }
        if (preg_match('/^[6-9]\d{9}$/', $digits)) {
            return $digits;
        }
        if (preg_match('/^\d{7,15}$/', $digits)) {
            return $digits;
        }
        return null;
    }

    public static function intRange($v, int $min, int $max, ?int $default = null): ?int
    {
        if ($v === null || $v === '') {
            return $default;
        }
        if (!is_numeric($v)) {
            return null;
        }
        $i = (int) $v;
        return ($i >= $min && $i <= $max) ? $i : null;
    }

    /** Allowlist for ORDER BY / sort params — prevents SQL injection via identifiers. */
    public static function sortKey(?string $v, array $allowed, string $default): string
    {
        return in_array($v, $allowed, true) ? $v : $default;
    }

    public static function enum(?string $v, array $allowed, ?string $default = null): ?string
    {
        return in_array($v, $allowed, true) ? $v : $default;
    }

    public static function text(string $v, int $maxLen = 2000): string
    {
        return mb_substr(trim($v), 0, $maxLen);
    }

    public static function slug(string $v): string
    {
        $v = strtolower(trim($v));
        $v = preg_replace('/[^a-z0-9]+/', '-', $v);
        return trim($v, '-');
    }

    /** Safe internal return path only (prevents open redirect). */
    public static function returnPath(?string $v, string $default = '/profile'): string
    {
        if (!$v || !is_string($v)) {
            return $default;
        }
        if ($v[0] !== '/' || strpos($v, '//') === 0 || strpos($v, '\\') !== false) {
            return $default;
        }
        if (preg_match('#^/(admin|api|app|storage|vendor|\.env)#', $v)) {
            // still allow admin paths only if already admin; default is fine
        }
        return substr($v, 0, 200);
    }
}
