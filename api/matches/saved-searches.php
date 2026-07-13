<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;
use Matrimony\Http\Request;

$userId = Auth::require();
$searchId = (int) (($_SERVER['API_PARAMS'][0] ?? $_GET['id'] ?? 0));

require_once __DIR__ . '/../../modules/matches/controllers/FilterController.php';
$controller = new FilterController();

if (Request::is('GET')) {
    if ($searchId) {
        $searches = $controller->getSavedSearches($userId);
        $search = current(array_filter($searches, fn($s) => (int) $s['id'] === $searchId));
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $search ?: null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $controller->getSavedSearches($userId)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (Request::is('POST')) {
    Csrf::require();
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $name = $input['name'] ?? '';
    $filters = $input['filters'] ?? [];
    $alert = !empty($input['alert_enabled']);
    if (!$name) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Name is required']);
        exit;
    }
    $result = $controller->saveSearch($userId, $name, $filters, $alert);
    header('Content-Type: application/json');
    echo json_encode(['success' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (Request::is('DELETE')) {
    Csrf::require();
    if (!$searchId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Search ID is required']);
        exit;
    }
    $result = $controller->deleteSearch($searchId, $userId);
    header('Content-Type: application/json');
    echo json_encode(['success' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(405);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
