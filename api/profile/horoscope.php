<?php
declare(strict_types=1);

use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;
use Matrimony\Http\Request;
use Matrimony\Services\ProfileService;

require_once __DIR__ . '/../../includes/bootstrap.php';

if (!Request::is('PUT')) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

Csrf::require();
$userId = Auth::require();
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
unset($input['csrf']);

$service = new ProfileService();
$result = $service->updateHoroscope($userId, $input);

http_response_code($result ? 200 : 400);
echo json_encode(['success' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
