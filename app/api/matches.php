<?php
declare(strict_types=1);

use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;

$userId = Auth::id();

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = (new Matrimony\Http\Request())->segment(3) ?? 'list';

require_once __DIR__ . '/../modules/matches/controllers/MatchController.php';
require_once __DIR__ . '/../modules/matches/controllers/ActionController.php';
require_once __DIR__ . '/../modules/matches/controllers/FilterController.php';

switch ($action) {
    case 'list':
        handleList($userId);
        break;
    case 'count':
        handleCount($userId);
        break;
    case 'profile':
        handleProfile($userId);
        break;
    case 'action':
        handleAction($userId);
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Not found']);
}

function handleList(int $userId): void {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(48, max(12, (int) ($_GET['per_page'] ?? 24)));
    $sort = $_GET['sort'] ?? 'compatibility';
    $rawFilters = $_GET['filters'] ?? '{}';
    $filters = is_string($rawFilters) ? (json_decode($rawFilters, true) ?: []) : (is_array($rawFilters) ? $rawFilters : []);

    $ctrl = new MatchController();
    $result = $ctrl->list($filters, $sort, $page, $perPage, $userId);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $result['data'],
        'meta' => $result['meta'],
        'facets' => $result['facets'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleCount(int $userId): void {
    $rawFilters = $_GET['filters'] ?? '{}';
    $filters = is_string($rawFilters) ? (json_decode($rawFilters, true) ?: []) : (is_array($rawFilters) ? $rawFilters : []);

    $ctrl = new FilterController();
    $count = $ctrl->getCount($filters, $userId);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => ['count' => $count]
    ]);
}

function handleProfile(int $userId): void {
    $profileId = (int) ($_GET['id'] ?? 0);
    if (!$profileId) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Profile not found']);
        return;
    }

    $ctrl = new MatchController();
    $profile = $ctrl->getProfile($profileId, $userId);

    if (!$profile) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Profile not found']);
        return;
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $profile
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleAction(int $userId): void {
    Csrf::require();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $targetId = (int) ($data['target_id'] ?? 0);
    $status = $data['status'] ?? 'interested';

    if (!$targetId || !$status) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'target_id and status are required']);
        return;
    }

    $ctrl = new ActionController();
    $result = $ctrl->perform($userId, $targetId, $status);

    header('Content-Type: application/json');
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
