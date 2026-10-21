<?php
declare(strict_types=1);

namespace Matrimony\Services;

use Matrimony\Database\Connection;
use Matrimony\Security\AuditService;

/**
 * Purchase lifecycle. Amounts ALWAYS come from the DB plan catalogue.
 * Browser result is informational only — only verified evidence counts.
 */
final class PaymentService
{
    private const TERMINAL = ['refunded', 'disputed', 'cancelled'];

    public static function publicReference(): string
    {
        return 'MTR' . strtoupper(bin2hex(random_bytes(6)));
    }

    public static function gatewayConfigured(): bool
    {
        try {
            return (new RazorpayClient())->configured();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Create local purchase + Razorpay order. */
    public static function createOrder(int $userId, int|string $planRef): array
    {
        $plan = PlanService::findActive($planRef);
        if (!$plan) {
            return ['success' => false, 'error' => 'Plan is not available'];
        }
        if (!self::gatewayConfigured()) {
            @error_log('PaymentService: Razorpay keys not configured; order refused.');
            return ['success' => false, 'error' => 'Online payments are not enabled yet. Please contact support.', 'code' => 'gateway_not_configured'];
        }
        $snap = PlanService::snapshot($plan);
        $currency = strtoupper((string) ($snap['currency'] ?? ''));
        if ((int) ($snap['amount_paise'] ?? 0) <= 0 || !preg_match('/^[A-Z]{3}$/', $currency)) {
            @error_log('PaymentService: plan ' . (string) ($plan['code'] ?? '?') . ' has an invalid amount/currency.');
            return ['success' => false, 'error' => 'This plan cannot be purchased right now. Please contact support.', 'code' => 'invalid_plan'];
        }

        $cfg = require BASE_PATH . '/app/config/payments.php';
        $client = new RazorpayClient();
        $mode = strtolower((string) ($cfg['razorpay']['mode'] ?? ''));
        $keyMode = $client->keyMode();
        $mismatch = self::modeMismatch($mode, $keyMode);
        if ($mismatch !== null) {
            @error_log('PaymentService: RAZORPAY_MODE=' . $mode . ' does not match the configured key type.');
            return ['success' => false, 'error' => $mismatch, 'code' => 'gateway_config_mismatch'];
        }

        $pdo = Connection::pdo();

        // Reuse a recent pending order for the same user+plan (avoids Razorpay order spam).
        $stmt = $pdo->prepare("SELECT id, public_reference, gateway_order_id, amount_paise, currency, plan_name_snapshot
            FROM purchases
            WHERE user_id = :u AND plan_id = :p AND payment_status = 'pending'
              AND gateway_order_id IS NOT NULL AND gateway_order_id != ''
              AND amount_paise = :amt
              AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)
            ORDER BY id DESC LIMIT 1");
        $stmt->execute([':u' => $userId, ':p' => $plan['id'], ':amt' => $snap['amount_paise']]);
        $existing = $stmt->fetch();
        if ($existing) {
            AuditService::log($userId, 'payment.order_reused', 'purchase', (int) $existing['id'], ['plan' => $plan['code']]);
            return [
                'success' => true,
                'purchase_id' => (int) $existing['id'],
                'public_reference' => $existing['public_reference'],
                'gateway_order_id' => $existing['gateway_order_id'],
                'key_id' => (string) $cfg['razorpay']['key_id'],
                'amount_paise' => (int) $existing['amount_paise'],
                'currency' => $existing['currency'],
                'plan_name' => $existing['plan_name_snapshot'],
                'reused' => true,
            ];
        }

        $ref = self::publicReference();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO purchases (public_reference, user_id, plan_id, plan_name_snapshot, amount_paise, currency, duration_days_snapshot, contact_limit_snapshot, is_unlimited_snapshot, gateway, payment_status)
                VALUES (:ref,:u,:p,:nm,:amt,:cur,:dur,:lim,:unl,'razorpay','created')");
            $stmt->execute([
                ':ref' => $ref, ':u' => $userId, ':p' => $plan['id'],
                ':nm' => $snap['plan_name_snapshot'], ':amt' => $snap['amount_paise'], ':cur' => $currency,
                ':dur' => $snap['duration_days_snapshot'], ':lim' => $snap['contact_limit_snapshot'], ':unl' => $snap['is_unlimited_snapshot'],
            ]);
            $purchaseId = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            @error_log('PaymentService: could not create purchase row (' . get_class($e) . ')');
            return ['success' => false, 'error' => 'Could not start purchase'];
        }

        try {
            $order = $client->createOrder((int) $snap['amount_paise'], $currency, $ref, ['user_id' => (string) $userId, 'plan' => (string) $plan['code']]);
        } catch (\Throwable $e) {
            @error_log('PaymentService: Razorpay order failed: ' . substr($e->getMessage(), 0, 200));
            $pdo->prepare("UPDATE purchases SET payment_status = 'failed', failure_reason = 'Gateway order creation failed' WHERE id = :id AND payment_status = 'created'")
                ->execute([':id' => $purchaseId]);
            AuditService::log($userId, 'payment.order_failed', 'purchase', $purchaseId, ['error' => substr($e->getMessage(), 0, 200)], 'failure');
            $purchase = ['id' => $purchaseId, 'user_id' => $userId, 'public_reference' => $ref, 'plan_name_snapshot' => $snap['plan_name_snapshot'], 'amount_paise' => $snap['amount_paise'], 'currency' => $currency];
            PaymentNotificationService::event('gateway.error', $purchase, ['reason' => 'The payment gateway could not create the order.', 'dedupe' => 'create:' . $purchaseId]);
            return ['success' => false, 'error' => 'Payment gateway is unavailable. Try again later.', 'code' => 'gateway_error'];
        }

        $pdo->prepare("UPDATE purchases SET gateway_order_id = :o, payment_status = 'pending' WHERE id = :id")
            ->execute([':o' => $order['id'], ':id' => $purchaseId]);
        AuditService::log($userId, 'payment.order_created', 'purchase', $purchaseId, ['plan' => $plan['code'], 'amount_paise' => $snap['amount_paise']]);

        PaymentNotificationService::event('payment.started', [
            'id' => $purchaseId,
            'user_id' => $userId,
            'public_reference' => $ref,
            'plan_name_snapshot' => $snap['plan_name_snapshot'],
            'amount_paise' => $snap['amount_paise'],
            'currency' => $currency,
        ], ['dedupe' => 'order:' . $order['id']]);

        return [
            'success' => true,
            'purchase_id' => $purchaseId,
            'public_reference' => $ref,
            'gateway_order_id' => $order['id'],
            'key_id' => (string) $cfg['razorpay']['key_id'],
            'amount_paise' => (int) $snap['amount_paise'],
            'currency' => $currency,
            'plan_name' => $snap['plan_name_snapshot'],
        ];
    }

    /**
     * Verify Checkout result server-side. On success → paid_verified +
     * activation request (NOT membership). Idempotent.
     */
    public static function verifyCheckout(int $userId, string $gwOrderId, string $gwPaymentId, string $signature): array
    {
        $gwOrderId = trim($gwOrderId);
        $gwPaymentId = trim($gwPaymentId);
        $signature = trim($signature);
        if ($gwOrderId === '' || $gwPaymentId === '' || $signature === '' || strlen($gwOrderId) > 80 || strlen($gwPaymentId) > 80 || strlen($signature) > 200) {
            return ['success' => false, 'error' => 'Invalid payment response.'];
        }
        $cfg = require BASE_PATH . '/app/config/payments.php';
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('SELECT * FROM purchases WHERE gateway_order_id = :o LIMIT 1');
        $stmt->execute([':o' => $gwOrderId]);
        $p = $stmt->fetch();
        if (!$p) {
            return ['success' => false, 'error' => 'Unknown order. It was flagged for review.'];
        }
        if ((int) $p['user_id'] !== $userId) {
            AuditService::log($userId, 'payment.verify_wrong_user', 'purchase', $p['id'], [], 'failure');
            return ['success' => false, 'error' => 'Order does not belong to this account.'];
        }
        // Terminal states must never be re-verified: a replay of the original
        // (still valid) signature must not undo a refund/dispute/cancellation.
        if (in_array($p['payment_status'], self::TERMINAL, true)) {
            AuditService::log($userId, 'payment.replay_blocked', 'purchase', $p['id'], ['status' => $p['payment_status']], 'failure');
            return ['success' => false, 'error' => 'This payment has already been resolved.'];
        }
        if ($p['payment_status'] === 'paid_verified') {
            return ['success' => true, 'already' => true, 'public_reference' => $p['public_reference']];
        }
        $secret = (string) ($cfg['razorpay']['key_secret'] ?? '');
        if ($secret === '' || !self::gatewayConfigured()) {
            AuditService::log($userId, 'payment.verify_misconfigured', 'purchase', $p['id'], [], 'failure');
            return ['success' => false, 'error' => 'Payment verification is temporarily unavailable. Please try again later.'];
        }
        $valid = RazorpayClient::verifyPaymentSignature($gwOrderId, $gwPaymentId, $signature, $secret);
        if (!$valid) {
            $reason = 'Checkout signature mismatch';
            self::flagReview($p, $gwPaymentId, $reason);
            AuditService::log($userId, 'payment.signature_invalid', 'purchase', $p['id'], ['order' => $gwOrderId], 'failure');
            PaymentNotificationService::event('payment.review', $p, ['reason' => 'The payment signature could not be verified.', 'dedupe' => 'sig:' . $gwPaymentId]);
            return ['success' => false, 'error' => 'Payment verification failed. It was flagged for review.'];
        }
        // Confirm capture + amount/currency with Razorpay API (signature alone is not enough).
        try {
            $client = new RazorpayClient();
            $gwPay = $client->fetchPayment($gwPaymentId);
        } catch (\Throwable $e) {
            @error_log('PaymentService: fetchPayment failed: ' . substr($e->getMessage(), 0, 200));
            self::flagReview($p, $gwPaymentId, 'Could not confirm capture with gateway');
            PaymentNotificationService::event('payment.review', $p, ['reason' => 'The gateway could not confirm the capture.', 'dedupe' => 'fetch:' . $gwPaymentId]);
            return ['success' => false, 'error' => 'Payment needs review. Our team will confirm it shortly.'];
        }
        return self::verifyGatewayPayment($p, $gwPay, $gwOrderId, $gwPaymentId, 'checkout');
    }

    /**
     * Shared gateway-evidence validation used by checkout verification and
     * reconciliation. Never trusts an empty/missing field.
     */
    public static function verifyGatewayPayment(array $p, array $gwPay, string $expectedOrderId, string $expectedPaymentId, string $via): array
    {
        $status = strtolower((string) ($gwPay['status'] ?? ''));
        $capturedRaw = $gwPay['captured'] ?? null;
        $captured = $capturedRaw === null
            ? ($status === 'captured')
            : ((int) $capturedRaw === 1 || $capturedRaw === true);
        $amount = (int) ($gwPay['amount'] ?? 0);
        $currency = strtoupper(trim((string) ($gwPay['currency'] ?? '')));
        $orderId = trim((string) ($gwPay['order_id'] ?? ''));
        $paymentId = trim((string) ($gwPay['id'] ?? ''));
        $expectedCurrency = strtoupper((string) $p['currency']);

        if ($status === 'authorized' && !$captured) {
            self::flagReview($p, $expectedPaymentId, 'Payment authorized but not captured');
            PaymentNotificationService::event('payment.review', $p, ['reason' => 'The payment is authorized but not captured.', 'dedupe' => 'uncaptured:' . $expectedPaymentId]);
            return ['success' => false, 'error' => 'Payment needs review. Our team will confirm it shortly.'];
        }
        if ($paymentId === '' || $paymentId !== $expectedPaymentId) {
            self::flagReview($p, $expectedPaymentId, 'Gateway payment id mismatch');
            PaymentNotificationService::event('payment.review', $p, ['reason' => 'The gateway payment reference did not match.', 'dedupe' => 'pid:' . $expectedPaymentId]);
            return ['success' => false, 'error' => 'Payment needs review. Our team will confirm it shortly.'];
        }
        if ($status !== 'captured' || !$captured || $amount <= 0
            || $amount !== (int) $p['amount_paise']
            || $currency === '' || $currency !== $expectedCurrency
            || $orderId === '' || $orderId !== $expectedOrderId
        ) {
            self::flagReview($p, $expectedPaymentId, 'Capture/amount/currency/order mismatch');
            AuditService::log((int) $p['user_id'], 'payment.capture_mismatch', 'purchase', $p['id'], [
                'status' => $status, 'amount' => $amount, 'currency' => $currency,
            ], 'failure');
            PaymentNotificationService::event('payment.review', $p, ['reason' => 'The captured amount, currency or order did not match the purchase.', 'dedupe' => 'mismatch:' . $expectedPaymentId]);
            return ['success' => false, 'error' => 'Payment needs review. Our team will confirm it shortly.'];
        }
        return self::markPaidVerified($p, $expectedPaymentId, $via);
    }

    /** Atomically move a purchase to needs_review without touching terminal states. */
    public static function flagReview(array $purchase, string $gwPaymentId, string $reason): void
    {
        try {
            Connection::pdo()->prepare("UPDATE purchases
                SET payment_status = 'needs_review',
                    gateway_payment_id = COALESCE(NULLIF(:pay, ''), gateway_payment_id),
                    failure_reason = :r
                WHERE id = :id AND payment_status NOT IN ('refunded','disputed','cancelled','paid_verified')")
                ->execute([
                    ':pay' => substr($gwPaymentId, 0, 80),
                    ':r' => substr($reason, 0, 255),
                    ':id' => $purchase['id'],
                ]);
        } catch (\Throwable $e) {
            @error_log('PaymentService::flagReview failed (' . get_class($e) . ')');
        }
    }

    /**
     * Mark a purchase paid_verified (single choke point for both
     * checkout-verify and webhook paths). Idempotent + transactional.
     */
    public static function markPaidVerified(array $purchase, string $gwPaymentId, string $via, array $evidence = []): array
    {
        $gwPaymentId = trim($gwPaymentId);
        if ($gwPaymentId === '' || strlen($gwPaymentId) > 80) {
            return ['success' => false, 'error' => 'Invalid payment reference'];
        }
        $pdo = Connection::pdo();
        $duplicate = false;
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT * FROM purchases WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $purchase['id']]);
            $p = $stmt->fetch();
            if (!$p) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'Purchase not found'];
            }
            if (in_array($p['payment_status'], self::TERMINAL, true)) {
                $pdo->rollBack();
                AuditService::log((int) $p['user_id'], 'payment.replay_blocked', 'purchase', $p['id'], ['status' => $p['payment_status'], 'via' => $via], 'failure');
                return ['success' => false, 'error' => 'This payment has already been resolved.'];
            }
            if ($p['payment_status'] === 'paid_verified') {
                $pdo->commit();
                if (!empty($p['gateway_payment_id']) && $p['gateway_payment_id'] !== $gwPaymentId) {
                    AuditService::log((int) $p['user_id'], 'payment.duplicate_payment_id', 'purchase', $p['id'], ['via' => $via], 'failure');
                    PaymentNotificationService::event('payment.review', $p, [
                        'reason' => 'A second captured payment reference was received for an already-paid order.',
                        'dedupe' => 'dup:' . $gwPaymentId,
                    ]);
                }
                return ['success' => true, 'already' => true, 'public_reference' => $p['public_reference']];
            }
            // A different, already-attached payment id means the order was reused.
            if (!empty($p['gateway_payment_id']) && $p['gateway_payment_id'] !== $gwPaymentId) {
                $duplicate = true;
            }
            $pdo->prepare("UPDATE purchases
                SET gateway_payment_id = :pay, payment_status = 'paid_verified',
                    signature_verified_at = UTC_TIMESTAMP(), paid_at = UTC_TIMESTAMP(), failure_reason = NULL
                WHERE id = :id")
                ->execute([':pay' => $gwPaymentId, ':id' => $p['id']]);
            $pdo->prepare('INSERT IGNORE INTO activation_requests (purchase_id, user_id, status) VALUES (:pid,:u,:s)')
                ->execute([':pid' => $p['id'], ':u' => $p['user_id'], ':s' => 'awaiting_admin']);
            $pdo->commit();
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (self::isDuplicateDbError($e)) {
                @error_log('PaymentService: payment reference is already linked to another purchase.');
                self::flagReview($purchase, '', 'Payment reference is linked to another purchase');
                PaymentNotificationService::admin('payment.review', $purchase, [
                    'reason' => 'A gateway payment reference was already linked to another purchase. Manual reconciliation is required.',
                    'dedupe' => 'duplicate-ref:' . $gwPaymentId,
                ]);
                return ['success' => false, 'error' => 'Payment needs manual review.'];
            }
            @error_log('PaymentService::markPaidVerified failed (' . get_class($e) . ')');
            return ['success' => false, 'error' => 'Could not confirm payment. It was flagged for review.'];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            @error_log('PaymentService::markPaidVerified failed (' . get_class($e) . ')');
            return ['success' => false, 'error' => 'Could not confirm payment. It was flagged for review.'];
        }
        $userId = (int) $p['user_id'];
        $p['payment_status'] = 'paid_verified';
        $p['gateway_payment_id'] = $gwPaymentId;
        $p['paid_at'] = gmdate('Y-m-d H:i:s');
        AuditService::log($userId, 'payment.verified', 'purchase', $p['id'], ['via' => $via, 'payment_id' => $gwPaymentId]);
        NotificationService::notify($userId, 'payment_verified', 'Payment verified', 'Payment ' . $p['public_reference'] . ' is verified and awaiting admin activation.', 'purchase', $p['id']);
        NotificationService::notifyAdmins('activation_needed', 'Payment awaiting activation', 'Purchase ' . $p['public_reference'] . ' (' . $p['plan_name_snapshot'] . ') is verified and awaiting activation.', 'purchase', $p['id']);

        if ($duplicate) {
            PaymentNotificationService::event('payment.review', $p, [
                'reason' => 'A different payment reference was attached to an order that already had one. Manual review required.',
                'dedupe' => 'dup-ref:' . $gwPaymentId,
            ]);
        } else {
            PaymentNotificationService::event('payment.paid', $p, ['dedupe' => $gwPaymentId]);
        }
        return ['success' => true, 'public_reference' => $p['public_reference']];
    }

    public static function forUser(int $userId, int $limit = 30): array
    {
        try {
            $pdo = Connection::pdo();
            $stmt = $pdo->prepare('SELECT p.*, ar.status AS activation_status,
                    m.id AS membership_id, m.status AS membership_status,
                    m.contact_limit_snapshot, m.is_unlimited_snapshot,
                    (SELECT COUNT(*) FROM contact_reveals cr WHERE cr.membership_id = m.id) AS membership_contacts_used
                FROM purchases p
                LEFT JOIN activation_requests ar ON ar.purchase_id = p.id
                LEFT JOIN memberships m ON m.purchase_id = p.id
                WHERE p.user_id = :u ORDER BY p.id DESC LIMIT ' . max(1, min(100, $limit)));
            $stmt->execute([':u' => $userId]);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return []; // tables missing pre-migration
        }
    }

    /** Admin-triggered safe reconciliation against gateway (no auto-activation). */
    public static function reconcile(int $adminId, int $purchaseId): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('SELECT * FROM purchases WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $purchaseId]);
        $p = $stmt->fetch();
        if (!$p) {
            return ['success' => false, 'error' => 'Purchase not found'];
        }
        if (empty($p['gateway_order_id'])) {
            return ['success' => false, 'error' => 'No gateway order linked'];
        }
        if (in_array((string) $p['payment_status'], ['refunded', 'disputed', 'cancelled'], true)) {
            return ['success' => true, 'note' => 'This purchase is already ' . $p['payment_status'] . '; no state change was made.'];
        }
        try {
            $client = new RazorpayClient();
            $order = $client->fetchOrder((string) $p['gateway_order_id']);
            $payments = $client->fetchOrderPayments((string) $p['gateway_order_id']);
        } catch (\Throwable $e) {
            @error_log('PaymentService::reconcile gateway error: ' . substr($e->getMessage(), 0, 200));
            PaymentNotificationService::admin('gateway.error', $p, ['reason' => 'The gateway could not be reached during reconciliation.', 'dedupe' => 'reconcile:' . $p['id'] . ':' . gmdate('YmdH')]);
            return ['success' => false, 'error' => 'Payment gateway is unreachable. Try again shortly.'];
        }

        $summary = [
            'order_id' => (string) ($order['id'] ?? ''),
            'order_status' => (string) ($order['status'] ?? ''),
            'amount' => (int) ($order['amount'] ?? 0),
            'currency' => strtoupper((string) ($order['currency'] ?? '')),
            'payments' => [],
        ];
        $captured = [];
        foreach ($payments as $pay) {
            if (!is_array($pay)) {
                continue;
            }
            $item = [
                'id' => (string) ($pay['id'] ?? ''),
                'status' => strtolower((string) ($pay['status'] ?? '')),
                'captured' => (int) ($pay['captured'] ?? 0) === 1,
                'amount' => (int) ($pay['amount'] ?? 0),
                'currency' => strtoupper((string) ($pay['currency'] ?? '')),
                'order_id' => (string) ($pay['order_id'] ?? ''),
            ];
            $summary['payments'][] = $item;
            if ($item['status'] === 'captured' || $item['captured']) {
                $captured[] = $item;
            }
        }
        AuditService::log($adminId, 'payment.reconcile', 'purchase', $purchaseId, [
            'gateway_status' => $summary['order_status'],
            'captured_payments' => count($captured),
        ]);

        if (count($captured) === 1) {
            return self::verifyGatewayPayment($p, $captured[0], (string) $p['gateway_order_id'], (string) $captured[0]['id'], 'reconcile');
        }
        if (count($captured) > 1) {
            self::flagReview($p, (string) $p['gateway_payment_id'], 'Multiple captured payments for one order');
            PaymentNotificationService::event('payment.review', $p, ['reason' => 'Multiple captured payments were found for a single order.', 'dedupe' => 'multi:' . $p['id']]);
            return ['success' => true, 'gateway' => $summary, 'note' => 'Multiple captured payments found. Flagged needs_review.'];
        }
        $note = $summary['order_status'] === 'paid'
            ? 'Gateway reports a paid order but no captured payment was returned. Verify manually.'
            : 'Gateway does not show a captured payment.';
        if ($summary['order_status'] === 'paid') {
            self::flagReview($p, (string) $p['gateway_payment_id'], 'Paid order without captured payment');
            PaymentNotificationService::event('payment.review', $p, ['reason' => $note, 'dedupe' => 'nopay:' . $p['id']]);
        }
        return ['success' => true, 'gateway' => $summary, 'note' => $note];
    }

    private static function isDuplicateDbError(\PDOException $e): bool
    {
        $info = $e->errorInfo ?? [];
        return (($info[0] ?? '') === '23000' && (int) ($info[1] ?? 0) === 1062)
            || str_contains($e->getMessage(), 'Duplicate entry');
    }

    private static function modeMismatch(string $mode, string $keyMode): ?string
    {
        if ($mode === '' || $keyMode === '') {
            return null;
        }
        if ($mode === 'test' && $keyMode !== 'test') {
            return 'Payment gateway is in test mode but a live key is configured. Contact support.';
        }
        if ($mode === 'live' && $keyMode !== 'live') {
            return 'Payment gateway is in live mode but a test key is configured. Contact support.';
        }
        return null;
    }
}
