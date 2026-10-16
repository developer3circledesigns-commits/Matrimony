<?php
declare(strict_types=1);
// GET /api/payments/status?ref=MTR... — own purchase status (display only, never proof)
require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Authorization;
use Matrimony\Database\Connection;

header('Content-Type: application/json');
$userId = Authorization::requireLogin();
$ref = substr(trim($_GET['ref'] ?? ''), 0, 40);
if ($ref === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ref is required']);
    exit;
}
$pdo = Connection::pdo();
$stmt = $pdo->prepare('SELECT p.*, ar.status AS activation_status FROM purchases p LEFT JOIN activation_requests ar ON ar.purchase_id = p.id WHERE p.public_reference = :r AND p.user_id = :u LIMIT 1');
$stmt->execute([':r' => $ref, ':u' => $userId]);
$p = $stmt->fetch();
if (!$p) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Not found']);
    exit;
}
unset($p['gateway_order_id']); // keep minimal? order id is needed by checkout only at creation; hide here
echo json_encode(['success' => true, 'purchase' => [
    'public_reference' => $p['public_reference'],
    'plan' => $p['plan_name_snapshot'],
    'amount_inr' => $p['amount_paise'] / 100,
    'payment_status' => $p['payment_status'],
    'activation_status' => $p['activation_status'],
    'created_at' => $p['created_at'],
]]);
