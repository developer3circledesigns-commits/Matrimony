<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;

header('Content-Type: application/json');

Csrf::require();
$userId = Auth::require();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$targetId = (int) ($input['target_id'] ?? 0);
$status = $input['status'] ?? '';

if (!$targetId || !$status) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'target_id and status are required']);
    exit;
}

try {
    require_once __DIR__ . '/../../modules/matches/controllers/ActionController.php';
    $controller = new ActionController();
    $result = $controller->perform($userId, $targetId, $status);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An internal error occurred']);
}
