<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;

Csrf::require();
$userId = Auth::require();

$params = $_SERVER['API_PARAMS'] ?? [];
$targetId = (int) ($params[0] ?? $_POST['target_id'] ?? 0);
$reason = $_POST['reason'] ?? '';

if (!$targetId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Target ID is required']);
    exit;
}

require_once __DIR__ . '/../../modules/matches/controllers/ActionController.php';
$controller = new ActionController();
$result = $controller->report($userId, $targetId, $reason);

header('Content-Type: application/json');
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
