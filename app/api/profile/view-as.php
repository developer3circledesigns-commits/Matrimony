<?php
declare(strict_types=1);

use Matrimony\Http\Auth;
use Matrimony\Services\ProfileService;

require_once __DIR__ . '/../../includes/bootstrap.php';

$userId = \Matrimony\Http\Auth::require();
$targetId = (int) ($_GET['id'] ?? 0);
$isAdmin = class_exists('Matrimony\Http\Authorization') && \Matrimony\Http\Authorization::isAdmin();
if ($userId !== $targetId && !$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

if (!$targetId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Target profile ID required']);
    exit;
}

$service = new ProfileService();
$profile = $service->getViewerProfile($targetId);

if (!$profile) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Profile not found']);
    exit;
}

$profile['age'] = $service->calcAge($profile['date_of_birth'] ?? '');
echo json_encode(['success' => true, 'data' => $profile], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
