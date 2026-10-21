<?php
declare(strict_types=1);
// POST /api/payments/create-order {plan: slug|id, csrf}
require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Authorization;
use Matrimony\Http\Csrf;
use Matrimony\Security\RateLimiter;
use Matrimony\Services\PaymentService;

header('Content-Type: application/json');
Csrf::require();
$userId = Authorization::requireLogin();
if (!RateLimiter::attempt(RateLimiter::keyFor('payment-order', (string) $userId), 10, 3600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many payment attempts. Try again later.']);
    exit;
}
$raw = file_get_contents('php://input', false, null, 0, 65536);
$in = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($in)) {
    $in = $_POST;
}
$plan = $in['plan'] ?? $in['plan_id'] ?? $in['slug'] ?? '';
if ($plan === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Plan is required']);
    exit;
}
$res = PaymentService::createOrder($userId, is_numeric($plan) ? (int) $plan : (string) $plan);
echo json_encode($res);
