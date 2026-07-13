<?php
declare(strict_types=1);

use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;
use Matrimony\Http\Request;
use Matrimony\Services\ProfileService;

require_once __DIR__ . '/../../includes/bootstrap.php';

$userId = Auth::require();
$service = new ProfileService();

if (Request::is('GET')) {
    $privacy = $service->getPrivacy($userId);
    echo json_encode(['success' => true, 'data' => $privacy], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (Request::is('PUT')) {
    Csrf::require();
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    unset($input['csrf']);
    try {
        $result = $service->updatePrivacy($userId, $input);
        if ($result === true) {
            $privacy = $service->getPrivacy($userId);
            echo json_encode(['success' => true, 'data' => $privacy], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => is_string($result) ? $result : 'Update failed. Check server error logs for details.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    } catch (\Throwable $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
