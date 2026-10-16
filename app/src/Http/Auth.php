<?php
namespace Matrimony\Http;

final class Auth
{
    public static function id(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function require(): int
    {
        $id = self::id();
        if ($id === null) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            exit;
        }
        return $id;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['logged_in_at'] = time();
        $_SESSION['admin_reauth_at'] = time();
        $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $_SESSION['last_activity'] = time();
        $_SESSION['ua'] = substr($_SERVER['HTTP_USER_AGENT'] ?? 'cli', 0, 255);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'] ?? '/', $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? true);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /** Validate session binding + idle timeout (1h user, enforced on each request). */
    public static function validateSession(int $idleSeconds = 3600): bool
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }
        // Do NOT pin to a UA substring: browsers can send slightly different
        // User-Agent strings on reload/prefetch, which caused spurious logouts.
        if (!empty($_SESSION['logged_in_at']) && (time() - (int) $_SESSION['logged_in_at']) > (7 * 24 * 3600)) {
            self::logout(); // absolute 7-day session life
            return false;
        }
        if (!empty($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > $idleSeconds) {
            self::logout();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    /** Revoke all web sessions for a user (password reset / suspension / role change). */
    public static function revokeUserSessions(int $userId): void
    {
        $dir = (defined('STORAGE_PATH') ? STORAGE_PATH : BASE_PATH . '/app/storage') . '/sessions';
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/sess_*') ?: [] as $f) {
            $c = @file_get_contents($f);
            if ($c !== false && strpos($c, 'user_id|i:' . $userId . ';') !== false) {
                @unlink($f);
            }
        }
        if (($_SESSION['user_id'] ?? null) === $userId) {
            self::logout();
        }
    }
}
