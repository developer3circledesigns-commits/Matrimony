<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Auth;

header('Content-Type: application/json');

$userId = Auth::require();

$filters = $_GET;
unset($filters['csrf']);

try {
    require_once __DIR__ . '/../../modules/matches/controllers/FilterController.php';
    require_once __DIR__ . '/../../modules/matches/controllers/MatchController.php';

    $filterCtrl = new FilterController();
    $count = $filterCtrl->getCount($filters, $userId);

    $matchCtrl = new MatchController();
    $list = $matchCtrl->list($filters, 'compatibility', 1, 24, $userId);

    echo json_encode([
        'success' => true,
        'data' => $list['data'],
        'meta' => ['total' => $count, 'page' => 1, 'per_page' => 24, 'took_ms' => $list['meta']['took_ms']],
        'facets' => $list['facets'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An internal error occurred']);
}
