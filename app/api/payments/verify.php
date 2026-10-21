<?php
declare(strict_types=1);
// POST /api/payments/verify {razorpay_order_id, razorpay_payment_id, razorpay_signature, csrf}
require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Authorization;
use Matrimony\Http\Csrf;
use Matrimony\Security\RateLimiter;
use Matrimony\Services\PaymentService;

header('Content-Type: application/json');
Csrf::require();
$userId = Authorization::requireLogin();
if (!RateLimiter::attempt('payment.verify:' . $userId, 20, 3600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many verification attempts. Please try again later.']);
    exit;
}
$raw = file_get_contents('php://input', false, null, 0, 65536);
$in = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($in)) {
    $in = $_POST;
}
$res = PaymentService::verifyCheckout(
    $userId,
    (string) ($in['razorpay_order_id'] ?? ''),
    (string) ($in['razorpay_payment_id'] ?? ''),
    (string) ($in['razorpay_signature'] ?? '')
);
echo json_encode($res);
