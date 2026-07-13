<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Auth;

$userId = Auth::require();

$filters = $_GET;
unset($filters['csrf'], $filters['_']);

require_once __DIR__ . '/../../modules/matches/controllers/FilterController.php';
$controller = new FilterController();
$count = $controller->getCount($filters, $userId);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => ['count' => $count],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
