<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;
use Matrimony\Http\Request;

$userId = Auth::require();

require_once __DIR__ . '/../../modules/matches/controllers/ActionController.php';
$controller = new ActionController();

if (Request::is('GET')) {
    $notifications = $controller->getNotifications($userId);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $notifications,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (Request::is('POST')) {
    Csrf::require();
    $controller->acknowledgeNotifications($userId);
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
