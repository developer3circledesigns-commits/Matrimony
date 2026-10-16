<?php
declare(strict_types=1);

namespace Matrimony\Http;

use Matrimony\Database\Connection;

/**
 * Server-side RBAC. Hiding buttons is not authorization —
 * every admin/API handler must call one of these guards.
 */
final class Authorization
{
    public const ROLE_USER = 'user';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SUPER_ADMIN = 'super_admin';

    /** @return array{id:int,role:string,account_status:string}|null */
    public static function currentUser(): ?array
    {
        $id = Auth::id();
        if ($id === null) {
            return null;
        }
        static $cache = [];
        if (isset($cache[$id])) {
            return $cache[$id];
        }
        try {
            $pdo = Connection::pdo();
            $stmt = $pdo->prepare('SELECT id, email, role, account_status FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row) {
                return null;
            }
            // Back-compat: old rows without role/account_status
            $row['role'] = $row['role'] ?? self::ROLE_USER;
            $row['account_status'] = $row['account_status'] ?? 'active';
            $cache[$id] = $row;
            return $row;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function role(): string
    {
        return self::currentUser()['role'] ?? self::ROLE_USER;
    }

    public static function isAdmin(): bool
    {
        return in_array(self::role(), [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true);
    }

    public static function isSuperAdmin(): bool
    {
        return self::role() === self::ROLE_SUPER_ADMIN;
    }

    public static function isModerator(): bool
    {
        return in_array(self::role(), [self::ROLE_MODERATOR, self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true);
    }

    /** Require login; 401 JSON for API, redirect for pages. */
    public static function requireLogin(string $redirect = '/users/login'): int
    {
        $id = Auth::id();
        if ($id === null) {
            if (self::wantsJson()) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }
            header('Location: ' . $redirect);
            exit;
        }
        // Suspended/deactivated accounts are denied immediately
        $u = self::currentUser();
        if ($u && in_array($u['account_status'], ['suspended', 'deactivated', 'deleted'], true)) {
            Auth::logout();
            if (self::wantsJson()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Account is not active. Contact support.']);
                exit;
            }
            header('Location: /users/login?error=' . urlencode('Account is not active. Contact support.'));
            exit;
        }
        return $id;
    }

    /** Require one of the given roles. */
    public static function requireRole(array $roles): array
    {
        $id = self::requireLogin('/admin/login');
        $u = self::currentUser();
        if (!$u || !in_array($u['role'], $roles, true)) {
            http_response_code(403);
            if (self::wantsJson()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Forbidden']);
            } else {
                header('Content-Type: text/html; charset=utf-8');
                echo '<h1>403 Forbidden</h1><p>You do not have permission to access this area.</p>';
            }
            exit;
        }
        return $u;
    }

    public static function requireAdmin(): array
    {
        $u = self::requireRole([self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN]);
        // Keep the high-risk reauth window fresh while the admin is actively
        // using the panel so saving forms never bounces to reauth mid-session.
        self::markReauth();
        return $u;
    }

    public static function requireSuperAdmin(): array
    {
        return self::requireRole([self::ROLE_SUPER_ADMIN]);
    }

    /** Ownership check — prevents IDOR. Admins with explicit perm bypass via $adminBypass. */
    public static function owns(int $ownerId, bool $adminBypass = false): bool
    {
        $id = Auth::id();
        if ($id === null) {
            return false;
        }
        if ($id === $ownerId) {
            return true;
        }
        if ($adminBypass && self::isAdmin()) {
            return true;
        }
        return false;
    }

    public static function requireOwner(int $ownerId, bool $adminBypass = false): int
    {
        $id = self::requireLogin();
        if (!self::owns($ownerId, $adminBypass)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            exit;
        }
        return $id;
    }

    /**
     * High-risk actions (role change, activation, refund) require recent
     * re-authentication (default 15 min). Call after requireAdmin().
     */
    public static function requireReauth(int $maxAgeSeconds = 14400): void
    {
        $ts = $_SESSION['admin_reauth_at'] ?? $_SESSION['logged_in_at'] ?? 0;
        if ((time() - (int) $ts) > $maxAgeSeconds) {
            if (self::wantsJson()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Reauthentication required', 'reauth_required' => true]);
                exit;
            }
            header('Location: /admin/reauth?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/admin'));
            exit;
        }
    }

    public static function markReauth(): void
    {
        $_SESSION['admin_reauth_at'] = time();
    }

    /**
     * True when the request is a native HTML <form> submission rather than an
     * XHR/fetch call. Such requests must be redirected (with a flash), never
     * handed a JSON body, even though they target an /api/ URL.
     */
    public static function isBrowserFormPost(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            return false;
        }
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        $isFormEncoded = strpos($contentType, 'application/x-www-form-urlencoded') !== false
            || strpos($contentType, 'multipart/form-data') !== false;
        if (!$isFormEncoded) {
            return false;
        }
        return strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') === false;
    }

    private static function wantsJson(): bool
    {
        if (PHP_SAPI === 'cli') {
            return true;
        }
        if (self::isBrowserFormPost()) {
            return false;
        }
        return strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false
            || strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0;
    }
}
