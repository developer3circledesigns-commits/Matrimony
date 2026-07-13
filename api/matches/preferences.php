<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;
use Matrimony\Http\Request;

$userId = Auth::require();

require_once __DIR__ . '/../../modules/matches/controllers/FilterController.php';
$controller = new FilterController();

if (Request::is('GET')) {
    $prefs = $controller->getPreferences($userId);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $prefs], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (Request::is('PUT')) {
    Csrf::require();
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $result = $controller->savePreferences($userId, $input);
    header('Content-Type: application/json');
    echo json_encode(['success' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(405);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
