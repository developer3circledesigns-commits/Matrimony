<?php
declare(strict_types=1);

use Matrimony\Http\Auth;
use Matrimony\Services\ProfileService;

require_once __DIR__ . '/../../includes/bootstrap.php';

$userId = Auth::require();
$service = new ProfileService();
$profile = $service->getFullProfile($userId);

if (!$profile) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Profile not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => [
        'percentage' => $profile['completion_percentage'],
        'fields' => $profile['completion_fields'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
