<?php
namespace Matrimony\Http;

final class Csrf
{
    private static ?array $parsedInput = null;

    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validate(?string $token): bool
    {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function require(): void
    {
        $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (empty($token) && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $input = json_decode($raw, true);
                if (is_array($input)) {
                    self::$parsedInput = $input;
                    // Populate $_POST so downstream code can read it after php://input is consumed
                    foreach ($input as $k => $v) {
                        if ($k !== 'csrf') {
                            $_POST[$k] = $v;
                        }
                    }
                    $token = $input['csrf'] ?? '';
                }
            }
        }

        if (!self::validate($token)) {
            $isAjax = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
                   || strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
            if ($isAjax) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid or missing CSRF token']);
                exit;
            }
            // For regular form submissions, redirect back so the user can retry
            $referer = $_SERVER['HTTP_REFERER'] ?? '/login';
            header('Location: ' . $referer . '?error=' . urlencode('Invalid or missing CSRF token'));
            exit;
        }
    }

    public static function hiddenField(): string
    {
        return '<input type="hidden" name="csrf" value="' . self::token() . '">';
    }
}
