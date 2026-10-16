<?php
declare(strict_types=1);

namespace Matrimony\Security;

use Matrimony\Database\Connection;

/**
 * DB-backed sliding-window rate limiter (shared-hosting safe, no daemon).
 * Falls back to file storage if table is missing.
 */
final class RateLimiter
{
    public static function hit(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        return self::attempt($key, $maxAttempts, $windowSeconds);
    }

    public static function attempt(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $key = substr(preg_replace('/[^a-zA-Z0-9_\-:.\/]/', '_', $key), 0, 190);
        try {
            $pdo = Connection::pdo();
            // Table is created by migration 012 — never DDL at request time.
            $stmt = $pdo->prepare('SELECT attempts, window_start FROM rate_limits WHERE `key` = :k LIMIT 1');
            $stmt->execute([':k' => $key]);
            $row = $stmt->fetch();
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            if (!$row) {
                $pdo->prepare('INSERT INTO rate_limits (`key`, attempts, window_start) VALUES (:k, 1, UTC_TIMESTAMP())')->execute([':k' => $key]);
                return true;
            }
            $start = new \DateTimeImmutable($row['window_start'], new \DateTimeZone('UTC'));
            if (($now->getTimestamp() - $start->getTimestamp()) > $windowSeconds) {
                $pdo->prepare('UPDATE rate_limits SET attempts = 1, window_start = UTC_TIMESTAMP() WHERE `key` = :k')->execute([':k' => $key]);
                return true;
            }
            if ((int) $row['attempts'] >= $maxAttempts) {
                return false;
            }
            $pdo->prepare('UPDATE rate_limits SET attempts = attempts + 1 WHERE `key` = :k')->execute([':k' => $key]);
            return true;
        } catch (\Throwable $e) {
            return self::fileAttempt($key, $maxAttempts, $windowSeconds);
        }
    }

    private static function fileAttempt(string $key, int $max, int $window): bool
    {
        $dir = BASE_PATH . '/app/storage/cache/ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . sha1($key) . '.json';
        $now = time();
        $data = ['count' => 0, 'start' => $now];
        if (is_file($file)) {
            $d = json_decode((string) @file_get_contents($file), true);
            if (is_array($d)) {
                $data = $d;
            }
        }
        if (($now - (int) ($data['start'] ?? $now)) > $window) {
            $data = ['count' => 1, 'start' => $now];
            @file_put_contents($file, json_encode($data), LOCK_EX);
            return true;
        }
        if ((int) ($data['count'] ?? 0) >= $max) {
            return false;
        }
        $data['count'] = (int) ($data['count'] ?? 0) + 1;
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return true;
    }

    public static function keyFor(string $action, ?string $subject = null): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        return $action . ':' . $ip . ($subject ? ':' . $subject : '');
    }
}
