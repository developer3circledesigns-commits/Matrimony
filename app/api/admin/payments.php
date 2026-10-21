<?php
declare(strict_types=1);
// POST /api/admin/payments/activate|hold|reject|reconcile|refund
//
// HTML form submissions get a redirect + flash (Post/Redirect/Get); XHR callers
// still receive JSON.
require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Authorization;
use Matrimony\Http\Csrf;
use Matrimony\Database\Connection;
use Matrimony\Security\AuditService;
use Matrimony\Services\MembershipService;
use Matrimony\Services\NotificationService;
use Matrimony\Services\PaymentNotificationService;
use Matrimony\Services\PaymentService;

admin_json_header();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    foreach ($in as $k => $v) {
        $_POST[$k] = $v;
    }
}
Csrf::require();
$admin = Authorization::requireAdmin();
Authorization::requireReauth(); // all payment actions are high-risk

$fallback = admin_return_to('/admin/payments?status=awaiting_admin');

if (str_ends_with($path, '/activate')) {
    $res = MembershipService::activate(
        (int) $admin['id'],
        (int) ($_POST['activation_id'] ?? 0),
        (string) ($_POST['mode'] ?? 'replace'),
        (string) ($_POST['reason'] ?? '')
    );
    $ok = !empty($res['success']);
    $res['message'] = $ok
        ? 'Membership activated. The member has been notified.'
        : 'Activation failed: ' . (string) ($res['error'] ?? 'unknown error');
    admin_respond($res, $fallback, $ok ? 200 : 422);
}

if (str_ends_with($path, '/hold') || str_ends_with($path, '/reject')) {
    $id = (int) ($_POST['activation_id'] ?? 0);
    $reason = substr(trim((string) ($_POST['reason'] ?? '')), 0, 500);
    if ($id <= 0 || $reason === '') {
        admin_respond([
            'success' => false,
            'error' => 'Activation ID and reason are required',
            'message' => 'A reason is required before an activation request can be held or rejected.',
        ], $fallback, 400);
    }
    $status = str_ends_with($path, '/hold') ? 'on_hold' : 'rejected';
    $pdo = Connection::pdo();
    $pdo->prepare('UPDATE activation_requests SET status = :s, reviewed_by = :a, reviewed_at = UTC_TIMESTAMP(), decision_reason = :r WHERE id = :id AND status IN (\'awaiting_admin\',\'on_hold\')')
        ->execute([':s' => $status, ':a' => $admin['id'], ':r' => $reason, ':id' => $id]);
    AuditService::log((int) $admin['id'], 'activation.' . $status, 'activation_request', $id, ['reason' => $reason]);
    $s = $pdo->prepare('SELECT ar.user_id AS activation_user_id, ar.purchase_id, p.* FROM activation_requests ar LEFT JOIN purchases p ON p.id = ar.purchase_id WHERE ar.id = :id LIMIT 1');
    $s->execute([':id' => $id]);
    if ($row = $s->fetch()) {
        $row['user_id'] = (int) ($row['activation_user_id'] ?? $row['user_id'] ?? 0);
        NotificationService::notify((int) $row['user_id'], 'activation_' . $status, 'Activation ' . $status, 'Your activation request was ' . $status . '. Reason: ' . $reason, 'purchase', $row['purchase_id']);
        PaymentNotificationService::event($status === 'on_hold' ? 'activation.hold' : 'activation.reject', $row, [
            'reason' => $reason,
            'dedupe' => 'activation:' . $id . ':' . $status,
        ]);
    }
    admin_respond([
        'success' => true,
        'status' => $status,
        'message' => 'Activation request ' . $status . '. The member has been notified with your reason.',
    ], $fallback);
}

if (str_ends_with($path, '/reconcile')) {
    $res = PaymentService::reconcile((int) $admin['id'], (int) ($_POST['purchase_id'] ?? 0));
    $ok = !empty($res['success']);
    $res['message'] = $ok
        ? (string) ($res['note'] ?? 'Payment reconciled with the gateway.')
        : 'Reconciliation failed: ' . (string) ($res['error'] ?? 'gateway unreachable');
    admin_respond($res, $fallback, 200);
}

if (str_ends_with($path, '/refund')) {
    // Create a refund record; gateway call is a manual ops step — never silently rewrites payment.
    $pid = (int) ($_POST['purchase_id'] ?? 0);
    $amount = (int) ($_POST['amount_paise'] ?? 0);
    $reason = substr(trim((string) ($_POST['reason'] ?? '')), 0, 500);
    if ($pid <= 0 || $amount <= 0 || $reason === '') {
        admin_respond([
            'success' => false,
            'error' => 'purchase_id, amount_paise and reason are required',
            'message' => 'A refund needs a purchase, an amount and a reason.',
        ], $fallback, 400);
    }
    $pdo = Connection::pdo();
    $s = $pdo->prepare('SELECT * FROM purchases WHERE id = :id LIMIT 1');
    $s->execute([':id' => $pid]);
    $purchase = $s->fetch();
    $purchaseAmt = (int) ($purchase['amount_paise'] ?? 0);
    if (!$purchase || $purchaseAmt <= 0) {
        admin_respond([
            'success' => false,
            'error' => 'Purchase not found',
            'message' => 'That purchase could not be found.',
        ], $fallback, 404);
    }
    if (!in_array((string) $purchase['payment_status'], ['paid_verified', 'disputed', 'refunded'], true)) {
        admin_respond([
            'success' => false,
            'error' => 'Purchase is not refundable',
            'message' => 'Only verified payments can be refunded. Current status: ' . (string) $purchase['payment_status'] . '.',
        ], $fallback, 422);
    }
    $rs = $pdo->prepare("SELECT COALESCE(SUM(amount_paise),0) FROM refunds WHERE purchase_id = :p AND status IN ('requested','processing','processed')");
    $rs->execute([':p' => $pid]);
    $alreadyRefunded = (int) $rs->fetchColumn();
    $remaining = max(0, $purchaseAmt - $alreadyRefunded);
    if ($amount > $remaining) {
        admin_respond([
            'success' => false,
            'error' => 'Amount exceeds refundable balance',
            'message' => 'Refund amount cannot exceed the remaining refundable balance (' . $remaining . ' paise).',
        ], $fallback, 400);
    }
    $pdo->prepare("INSERT INTO refunds (purchase_id, amount_paise, status, reason, requested_by) VALUES (:p,:a,'requested',:r,:b)")
        ->execute([':p' => $pid, ':a' => $amount, ':r' => $reason, ':b' => $admin['id']]);
    $refundId = (int) $pdo->lastInsertId();
    AuditService::log((int) $admin['id'], 'refund.requested', 'purchase', $pid, ['amount_paise' => $amount, 'reason' => $reason]);
    PaymentNotificationService::event('refund.requested', $purchase, [
        'refund_amount_paise' => $amount,
        'reason' => $reason,
        'dedupe' => 'adminrefund:' . $refundId,
    ]);
    admin_respond([
        'success' => true,
        'refund_id' => $refundId,
        'message' => 'Refund recorded as requested. Process it in the Razorpay dashboard; the webhook will settle the purchase and membership state.',
    ], $fallback);
}

admin_respond(['success' => false, 'error' => 'Not found', 'message' => 'Unknown payment action.'], $fallback, 404);
