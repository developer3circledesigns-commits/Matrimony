<?php
declare(strict_types=1);

use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;
use Matrimony\Http\Request;
use Matrimony\Database\Connection;

require_once __DIR__ . '/../../includes/bootstrap.php';

if (!Request::is('POST')) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

Csrf::require();

$userId = Auth::require();
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$password = $input['password'] ?? '';

if (empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Password is required to reactivate']);
    exit;
}

try {
    $pdo = Connection::pdo();
    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE id = :id AND is_active = 0");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Account is not deactivated or already active']);
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Incorrect password']);
        exit;
    }

    $pdo->prepare("UPDATE users SET is_active = 1, deactivated_at = NULL, deactivation_reason = NULL WHERE id = :id")
        ->execute([':id' => $userId]);

    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
