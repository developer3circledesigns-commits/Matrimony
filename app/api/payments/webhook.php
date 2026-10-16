<?php
declare(strict_types=1);
// POST /api/payments/webhook — Razorpay server-to-server. NO CSRF (gateway signature instead).
require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Services\WebhookService;

header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}
$raw = file_get_contents('php://input');
$headers = [];
foreach ($_SERVER as $k => $v) {
    if (str_starts_with($k, 'HTTP_')) {
        $name = strtolower(str_replace('_', '-', substr($k, 5)));
        $headers[$name] = $v;
    }
}
$res = WebhookService::handle((string) $raw, $headers);
echo json_encode($res);
