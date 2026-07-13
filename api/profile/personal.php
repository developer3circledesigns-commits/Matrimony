<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;
use Matrimony\Http\Request;
use Matrimony\Services\ProfileService;

if (!Request::is('PUT')) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

Csrf::require();
$userId = Auth::require();
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
unset($input['csrf']);

try {
    $service = new ProfileService();
    $result = $service->updatePersonal($userId, $input);
    if ($result === true) {
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => is_string($result) ? $result : 'Update failed. Check server error logs for details.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
