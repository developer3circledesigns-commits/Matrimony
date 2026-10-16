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
        $pdo = Connection::pdo();
        $snap = PlanService::snapshot($plan);
        $cfg = require BASE_PATH . '/app/config/payments.php';

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
                'key_id' => $cfg['razorpay']['key_id'],
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
                ':nm' => $snap['plan_name_snapshot'], ':amt' => $snap['amount_paise'], ':cur' => $snap['currency'],
                ':dur' => $snap['duration_days_snapshot'], ':lim' => $snap['contact_limit_snapshot'], ':unl' => $snap['is_unlimited_snapshot'],
            ]);
            $purchaseId = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'error' => 'Could not start purchase'];
        }
        try {
            $client = new RazorpayClient();
            $order = $client->createOrder((int) $snap['amount_paise'], $snap['currency'], $ref, ['user_id' => (string) $userId, 'plan' => $plan['code']]);
            $pdo->prepare("UPDATE purchases SET gateway_order_id = :o, payment_status = 'pending' WHERE id = :id")
                ->execute([':o' => $order['id'], ':id' => $purchaseId]);
        } catch (\Throwable $e) {
            @error_log('PaymentService: Razorpay order failed: ' . substr($e->getMessage(), 0, 300));
            $pdo->prepare("UPDATE purchases SET payment_status = 'failed', failure_reason = :r WHERE id = :id")
                ->execute([':r' => substr('Order creation failed: ' . $e->getMessage(), 0, 255), ':id' => $purchaseId]);
            AuditService::log($userId, 'payment.order_failed', 'purchase', $purchaseId, ['error' => substr($e->getMessage(), 0, 200)], 'failure');
            return ['success' => false, 'error' => 'Payment gateway is unavailable. Try again later.', 'code' => 'gateway_error'];
        }
        AuditService::log($userId, 'payment.order_created', 'purchase', $purchaseId, ['plan' => $plan['code'], 'amount_paise' => $snap['amount_paise']]);
        return [
            'success' => true,
            'purchase_id' => $purchaseId,
            'public_reference' => $ref,
            'gateway_order_id' => $order['id'],
            'key_id' => $cfg['razorpay']['key_id'],
            'amount_paise' => $snap['amount_paise'],
            'currency' => $snap['currency'],
            'plan_name' => $snap['plan_name_snapshot'],
        ];
    }

    /**
     * Verify Checkout result server-side. On success → paid_verified +
     * activation request (NOT membership). Idempotent.
     */
    public static function verifyCheckout(int $userId, string $gwOrderId, string $gwPaymentId, string $signature): array
    {
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
        if (in_array($p['payment_status'], ['refunded', 'disputed', 'cancelled'], true)) {
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
            try {
                $pdo->prepare("UPDATE purchases SET payment_status = 'needs_review', gateway_payment_id = :pay, failure_reason = 'Checkout signature mismatch' WHERE id = :id")
                    ->execute([':pay' => $gwPaymentId, ':id' => $p['id']]);
            } catch (\Throwable $e) {
                @error_log('PaymentService: could not attach payment id on review: ' . $e->getMessage());
            }
            AuditService::log($userId, 'payment.signature_invalid', 'purchase', $p['id'], ['order' => $gwOrderId], 'failure');
            NotificationService::notify($userId, 'payment_needs_review', 'Payment needs review', 'Your payment signature did not verify. Our team will review it.', 'purchase', $p['id']);
            return ['success' => false, 'error' => 'Payment verification failed. It was flagged for review.'];
        }
        // Confirm capture + amount/currency with Razorpay API (signature alone is not enough).
        try {
            $client = new RazorpayClient();
            $gwPay = $client->fetchPayment($gwPaymentId);
        } catch (\Throwable $e) {
            @error_log('PaymentService: fetchPayment failed: ' . substr($e->getMessage(), 0, 200));
            $pdo->prepare("UPDATE purchases SET payment_status = 'needs_review', gateway_payment_id = :pay, failure_reason = 'Could not confirm capture with gateway' WHERE id = :id")
                ->execute([':pay' => $gwPaymentId, ':id' => $p['id']]);
            NotificationService::notifyAdmins('payment_review', 'Checkout needs review', 'Purchase ' . $p['public_reference'] . ' signature OK but gateway fetch failed.', 'purchase', $p['id']);
            return ['success' => false, 'error' => 'Payment needs review. Our team will confirm it shortly.'];
        }
        $gwStatus = strtolower((string) ($gwPay['status'] ?? ''));
        $gwAmount = (int) ($gwPay['amount'] ?? 0);
        $gwCurrency = strtoupper((string) ($gwPay['currency'] ?? ''));
        $gwOrder = (string) ($gwPay['order_id'] ?? '');
        // Authorized-only (manual capture) must not entitle — flag for ops.
        if ($gwStatus === 'authorized' && (int) ($gwPay['captured'] ?? 0) !== 1) {
            $pdo->prepare("UPDATE purchases SET payment_status = 'needs_review', gateway_payment_id = :pay, failure_reason = 'Payment authorized but not captured' WHERE id = :id")
                ->execute([':pay' => $gwPaymentId, ':id' => $p['id']]);
            AuditService::log($userId, 'payment.not_captured', 'purchase', $p['id'], ['status' => $gwStatus], 'failure');
            NotificationService::notifyAdmins('payment_review', 'Uncaptured payment', 'Purchase ' . $p['public_reference'] . ' is authorized but not captured.', 'purchase', $p['id']);
            return ['success' => false, 'error' => 'Payment needs review. Our team will confirm it shortly.'];
        }
        if ($gwStatus !== 'captured'
            || $gwAmount !== (int) $p['amount_paise']
            || ($gwCurrency !== '' && $gwCurrency !== strtoupper((string) $p['currency']))
            || ($gwOrder !== '' && $gwOrder !== $gwOrderId)
        ) {
            $pdo->prepare("UPDATE purchases SET payment_status = 'needs_review', gateway_payment_id = :pay, failure_reason = 'Capture/amount/currency mismatch' WHERE id = :id")
                ->execute([':pay' => $gwPaymentId, ':id' => $p['id']]);
            AuditService::log($userId, 'payment.capture_mismatch', 'purchase', $p['id'], [
                'status' => $gwStatus, 'amount' => $gwAmount, 'currency' => $gwCurrency,
            ], 'failure');
            NotificationService::notifyAdmins('payment_review', 'Payment mismatch', 'Purchase ' . $p['public_reference'] . ' failed capture/amount checks.', 'purchase', $p['id']);
            return ['success' => false, 'error' => 'Payment needs review. Our team will confirm it shortly.'];
        }
        return self::markPaidVerified($p, $gwPaymentId, 'checkout');
    }

    /**
     * Mark a purchase paid_verified (single choke point for both
     * checkout-verify and webhook paths). Idempotent + transactional.
     */
    public static function markPaidVerified(array $purchase, string $gwPaymentId, string $via): array
    {
        $pdo = Connection::pdo();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT * FROM purchases WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $purchase['id']]);
            $p = $stmt->fetch();
            if (!$p) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'Purchase not found'];
            }
            // Another payment id on same order → needs review (possible tampering/reuse)
            if (!empty($p['gateway_payment_id']) && $p['gateway_payment_id'] !== $gwPaymentId && $p['payment_status'] === 'paid_verified') {
                $pdo->rollBack();
                AuditService::log((int) $p['user_id'], 'payment.duplicate_payment_id', 'purchase', $p['id'], ['via' => $via], 'failure');
                return ['success' => true, 'already' => true, 'public_reference' => $p['public_reference']];
            }
            // Never move a resolved purchase back into the paid pipeline.
            if (in_array($p['payment_status'], ['refunded', 'disputed', 'cancelled'], true)) {
                $pdo->rollBack();
                AuditService::log((int) $p['user_id'], 'payment.replay_blocked', 'purchase', $p['id'], ['status' => $p['payment_status'], 'via' => $via], 'failure');
                return ['success' => false, 'error' => 'This payment has already been resolved.'];
            }
            if ($p['payment_status'] === 'paid_verified') {
                $pdo->commit();
                return ['success' => true, 'already' => true, 'public_reference' => $p['public_reference']];
            }
            $pdo->prepare("UPDATE purchases SET gateway_payment_id = :pay, payment_status = 'paid_verified', signature_verified_at = UTC_TIMESTAMP(), paid_at = UTC_TIMESTAMP() WHERE id = :id")
                ->execute([':pay' => $gwPaymentId, ':id' => $p['id']]);
            $pdo->prepare('INSERT IGNORE INTO activation_requests (purchase_id, user_id, status) VALUES (:pid,:u,:s)')
                ->execute([':pid' => $p['id'], ':u' => $p['user_id'], ':s' => 'awaiting_admin']);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'error' => 'Could not confirm payment. It was flagged for review.'];
        }
        $userId = (int) $purchase['user_id'];
        AuditService::log($userId, 'payment.verified', 'purchase', $purchase['id'], ['via' => $via, 'payment_id' => $gwPaymentId]);
        NotificationService::notify($userId, 'payment_verified', 'Payment verified', 'Payment ' . $purchase['public_reference'] . ' is verified and awaiting admin activation.', 'purchase', $purchase['id']);
        NotificationService::notifyAdmins('activation_needed', 'Payment awaiting activation', 'Purchase ' . $purchase['public_reference'] . ' (' . $purchase['plan_name_snapshot'] . ') is verified and awaiting activation.', 'purchase', $purchase['id']);
        EmailOutboxService::queue($userId, 'payment_received_pending', $purchase);
        EmailOutboxService::queueToAdmins('admin_activation_needed', $purchase);
        return ['success' => true, 'public_reference' => $purchase['public_reference']];
    }

    public static function forUser(int $userId, int $limit = 30): array
    {
        try {
            $pdo = Connection::pdo();
            $stmt = $pdo->prepare('SELECT p.*, ar.status AS activation_status FROM purchases p LEFT JOIN activation_requests ar ON ar.purchase_id = p.id WHERE p.user_id = :u ORDER BY p.id DESC LIMIT ' . max(1, min(100, $limit)));
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
        try {
            $client = new RazorpayClient();
            $order = $client->fetchOrder($p['gateway_order_id']);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Gateway unreachable: ' . substr($e->getMessage(), 0, 150)];
        }
        AuditService::log($adminId, 'payment.reconcile', 'purchase', $purchaseId, ['gateway_status' => $order['status'] ?? 'unknown']);
        $paid = in_array($order['status'] ?? '', ['paid', 'captured'], true);
        $amountOk = ((int) ($order['amount'] ?? 0)) === (int) $p['amount_paise'];
        if ($paid && $amountOk && !empty($p['gateway_payment_id'])) {
            return ['success' => true, 'gateway' => $order, 'note' => 'Gateway shows paid. If signature/webhook evidence is missing, verify manually before activation.'];
        }
        return ['success' => true, 'gateway' => $order, 'note' => $paid ? 'Amount/status differs — flagged needs_review.' : 'Gateway does not show a captured payment.'];
    }
}
