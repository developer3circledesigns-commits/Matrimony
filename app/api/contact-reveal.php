<?php
declare(strict_types=1);
// POST /api/contact-reveal {target_id, csrf} — protected contact, membership-gated
require_once __DIR__ . '/../includes/bootstrap.php';

use Matrimony\Http\Authorization;
use Matrimony\Http\Csrf;
use Matrimony\Services\ContactRevealService;

header('Content-Type: application/json');
Csrf::require();
$viewer = Authorization::requireLogin();
$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$target = (int) ($in['target_id'] ?? 0);
if ($target <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'target_id is required']);
    exit;
}
$res = ContactRevealService::reveal($viewer, $target);
echo json_encode($res);
