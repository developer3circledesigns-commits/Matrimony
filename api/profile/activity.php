<?php
declare(strict_types=1);

use Matrimony\Http\Auth;
use Matrimony\Services\ProfileService;

require_once __DIR__ . '/../../includes/bootstrap.php';

$userId = Auth::require();
$limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));

$service = new ProfileService();
$activity = $service->getActivity($userId, $limit);

echo json_encode(['success' => true, 'data' => $activity], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
