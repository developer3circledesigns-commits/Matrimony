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
$reason = trim($input['reason'] ?? '');

try {
    $pdo = Connection::pdo();
    $stmt = $pdo->prepare("UPDATE users SET is_active = 0, deactivated_at = NOW(), deactivation_reason = :reason WHERE id = :id AND is_active = 1");
    $stmt->execute([':reason' => $reason ?: null, ':id' => $userId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Account is already deactivated']);
        exit;
    }

    Auth::logout();

    echo json_encode(['success' => true, 'redirect' => '/']);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
