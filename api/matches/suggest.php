<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Auth;
use Matrimony\Services\CompatibilityService;

$userId = Auth::require();

$service = new CompatibilityService();
$suggestions = $service->getTopMatches($userId, 12);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $suggestions,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
