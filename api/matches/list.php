<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Auth;

header('Content-Type: application/json');

$userId = Auth::require();

$rawFilters = $_REQUEST['filters'] ?? '';
$filters = is_string($rawFilters) && $rawFilters !== ''
    ? (json_decode($rawFilters, true) ?: [])
    : (is_array($rawFilters) ? $rawFilters : []);

$sort   = $_REQUEST['sort']    ?? 'compatibility';
$page   = max(1, (int) ($_REQUEST['page']     ?? 1));
$perPage = min(48, max(12, (int) ($_REQUEST['per_page'] ?? 24)));

try {
    require_once __DIR__ . '/../../modules/matches/controllers/MatchController.php';
    $controller = new MatchController();
    $result = $controller->list($filters, $sort, $page, $perPage, $userId);

    echo json_encode([
        'success' => true,
        'data' => $result['data'],
        'meta' => $result['meta'],
        'facets' => $result['facets'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    error_log('Matches list error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
