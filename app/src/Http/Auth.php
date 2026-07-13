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
        $env = getenv('APP_ENV') ?: 'production';
        if ($env === 'production') {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $userId;
        $_SESSION['logged_in_at'] = time();
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id']);
        session_destroy();
    }
}
