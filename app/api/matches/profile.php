<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;
use Matrimony\Http\Request;

$userId = Auth::require();
$params = $_SERVER['API_PARAMS'] ?? [];
$targetId = (int) ($params[0] ?? $_GET['id'] ?? 0);

if (!$targetId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Profile ID is required']);
    exit;
}

require_once __DIR__ . '/../../modules/matches/controllers/MatchController.php';
require_once __DIR__ . '/../../modules/matches/controllers/ActionController.php';

$matchCtrl = new MatchController();
$actionCtrl = new ActionController();

// Log view on GET
if (Request::is('GET')) {
    $actionCtrl->logView($userId, $targetId);
    $profile = $matchCtrl->getProfile($targetId, $userId);

    header('Content-Type: application/json');
    if (!$profile) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Profile not found']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $profile], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// POST with /view sub-action
if (Request::is('POST') && ($params[1] ?? '') === 'view') {
    Csrf::require();
    $actionCtrl->logView($userId, $targetId);
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
