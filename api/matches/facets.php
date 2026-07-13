<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Auth;

$userId = Auth::require();
$filters = $_GET;
unset($filters['csrf'], $filters['_']);

require_once __DIR__ . '/../../modules/matches/controllers/MatchController.php';
$controller = new MatchController();
$result = $controller->list($filters, 'compatibility', 1, 1, $userId);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $result['facets'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
