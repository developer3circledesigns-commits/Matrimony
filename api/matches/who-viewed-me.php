<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Auth;

$userId = Auth::require();
$limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));

require_once __DIR__ . '/../../modules/matches/controllers/ActionController.php';
$controller = new ActionController();
$viewers = $controller->getWhoViewedMe($userId, $limit);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $viewers,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
