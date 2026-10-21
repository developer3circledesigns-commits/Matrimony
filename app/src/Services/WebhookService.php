<?php
declare(strict_types=1);

namespace Matrimony\Services;

use Matrimony\Database\Connection;
use Matrimony\Database\Schema;
use Matrimony\Security\AuditService;

/**
 * Razorpay webhook processing: raw-body signature, idempotency,
 * amount/currency validation. Never activates membership.
 *
 * Provider events handled:
 *  payment.captured, order.paid, payment.authorized, payment.failed
 *  refund.created, refund.processed, refund.failed, payment.refunded
 *  payment.dispute.created|under_review|won|lost|closed|updated
 */
final class WebhookService
{
    private const PAID_EVENTS = ['payment.captured', 'order.paid'];
    private const AUTHORIZED_EVENTS = ['payment.authorized'];
    private const FAILED_EVENTS = ['payment.failed'];
    private const REFUND_EVENTS = ['refund.created', 'refund.processed', 'refund.failed', 'refund.speed_changed', 'payment.refunded'];
    private const DISPUTE_EVENTS = [
        'payment.dispute.created', 'payment.dispute.under_review', 'payment.dispute.won',
        'payment.dispute.lost', 'payment.dispute.closed', 'payment.dispute.updated',
        // Legacy aliases still seen on some accounts.
        'payment.disputed', 'dispute.created', 'dispute.updated', 'dispute.under_review',
        'dispute.won', 'dispute.lost', 'dispute.closed',
    ];

    public static function handle(string $rawBody, array $headers): array
    {
        $cfg = require BASE_PATH . '/app/config/payments.php';
        $secret = (string) ($cfg['razorpay']['webhook_secret'] ?? '');
        $signature = $headers['x-razorpay-signature'] ?? $headers['X-Razorpay-Signature'] ?? '';
        if ($secret === '') {
            http_response_code(500);
            return ['ok' => false, 'error' => 'Webhook secret not configured'];
        }
        if (!RazorpayClient::verifyWebhookSignature($rawBody, (string) $signature, $secret)) {
            AuditService::log(null, 'webhook.invalid_signature', 'webhook', null, [], 'failure');
            http_response_code(400);
            return ['ok' => false, 'error' => 'Invalid signature'];
        }
        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            http_response_code(400);
            return ['ok' => false, 'error' => 'Malformed payload'];
        }
        $eventType = substr((string) ($event['event'] ?? ''), 0, 80);
        if ($eventType === '') {
            http_response_code(400);
            return ['ok' => false, 'error' => 'Missing event type'];
        }
        $providerKey = trim((string) ($event['id'] ?? ''));
        if ($providerKey === '') {
            $providerKey = 'evt_' . hash('sha256', $rawBody);
        }
        $providerKey = substr($providerKey, 0, 120);

        $pdo = Connection::pdo();
        $eventId = self::recordEvent($pdo, $providerKey, $eventType, $rawBody, $event);
        if ($eventId === null) {
            http_response_code(200);
            return ['ok' => true, 'duplicate' => true];
        }
        if ($eventId < 0) {
            http_response_code(500);
            return ['ok' => false, 'error' => 'Could not record event'];
        }

        try {
            $result = self::process($pdo, $eventId, $eventType, $event);
        } catch (\Throwable $e) {
            @error_log('WebhookService: ' . $eventType . ' failed (' . get_class($e) . ')');
            self::finish($pdo, $eventId, 'failed', 'Processing failed');
            PaymentNotificationService::admin('security.webhook', [], ['reason' => 'A webhook raised an unexpected processing error.', 'dedupe' => 'whfail:' . $providerKey]);
            http_response_code(500);
            return ['ok' => false, 'error' => 'Webhook processing failed'];
        }

        self::finish($pdo, $eventId, $result['status'], $result['message'] ?? null);
        http_response_code(200);
        return array_merge(['ok' => true], $result['response'] ?? []);
    }

    /**
     * Insert the idempotency row. Returns the event id, null when the event was
     * already fully processed, or -1 when it could not be stored.
     */
    private static function recordEvent(\PDO $pdo, string $providerKey, string $eventType, string $rawBody, array $event): ?int
    {
        $summary = self::summary($event);
        $hash = hash('sha256', $rawBody);
        $hasSummary = Schema::has('gateway_webhook_events', 'payload_summary');
        $cols = ['gateway', 'provider_event_key', 'event_type', 'payload_hash', 'processing_status'];
        $vals = [':g', ':k', ':t', ':h', ':s'];
        $bind = [':g' => 'razorpay', ':k' => $providerKey, ':t' => $eventType, ':h' => $hash, ':s' => 'received'];
        $cols[] = $hasSummary ? 'payload_summary' : 'payload_redacted';
        $vals[] = ':p';
        $bind[':p'] = json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}';

        try {
            $pdo->prepare('INSERT INTO gateway_webhook_events (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')')
                ->execute($bind);
            return (int) $pdo->lastInsertId();
        } catch (\PDOException $e) {
            if (!self::isDuplicate($e)) {
                @error_log('WebhookService: could not record event (' . $e->getCode() . ')');
                return -1;
            }
        } catch (\Throwable $e) {
            @error_log('WebhookService: could not record event (' . get_class($e) . ')');
            return -1;
        }

        $s = $pdo->prepare("SELECT id, processing_status FROM gateway_webhook_events WHERE gateway = 'razorpay' AND provider_event_key = :k LIMIT 1");
        $s->execute([':k' => $providerKey]);
        $prev = $s->fetch();
        if (!$prev) {
            return null;
        }
        if (in_array((string) $prev['processing_status'], ['processed', 'ignored'], true)) {
            return null;
        }
        $pdo->prepare("UPDATE gateway_webhook_events SET processing_status = 'received', error_message = NULL, event_type = :t, payload_hash = :h"
            . ($hasSummary ? ', payload_summary = :p' : ', payload_redacted = :p')
            . ' WHERE id = :id')
            ->execute([':t' => $eventType, ':h' => $hash, ':p' => $bind[':p'], ':id' => $prev['id']]);
        return (int) $prev['id'];
    }

    /** @return array{status:string,message:?string,response?:array} */
    private static function process(\PDO $pdo, int $eventId, string $eventType, array $event): array
    {
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
        $payment = $payload['payment']['entity'] ?? null;
        $order = $payload['order']['entity'] ?? null;
        $refund = $payload['refund']['entity'] ?? null;
        $dispute = $payload['dispute']['entity'] ?? null;

        $payment = is_array($payment) ? $payment : null;
        $order = is_array($order) ? $order : null;
        $refund = is_array($refund) ? $refund : null;
        $dispute = is_array($dispute) ? $dispute : null;

        $orderId = '';
        $paymentId = '';
        if ($payment !== null) {
            $orderId = (string) ($payment['order_id'] ?? '');
            $paymentId = (string) ($payment['id'] ?? '');
        }
        if ($order !== null) {
            $orderId = $orderId !== '' ? $orderId : (string) ($order['id'] ?? '');
        }
        if ($refund !== null) {
            $paymentId = $paymentId !== '' ? $paymentId : (string) ($refund['payment_id'] ?? '');
        }
        if ($dispute !== null) {
            $paymentId = $paymentId !== '' ? $paymentId : (string) ($dispute['payment_id'] ?? '');
        }

        $purchase = self::findPurchase($pdo, $orderId, $paymentId);
        if (!$purchase && $paymentId === '' && $refund !== null) {
            $purchase = self::findPurchaseByGatewayLookup($pdo, $refund, 'refund');
            $paymentId = (string) ($purchase['gateway_payment_id'] ?? '');
        }
        if (!$purchase && $paymentId === '' && $dispute !== null) {
            $purchase = self::findPurchaseByGatewayLookup($pdo, $dispute, 'dispute');
            $paymentId = (string) ($purchase['gateway_payment_id'] ?? '');
        }
        if (!$purchase) {
            AuditService::log(null, 'webhook.unknown_order', 'webhook', $eventId, ['order' => substr($orderId, 0, 40)], 'failure');
            if (in_array($eventType, array_merge(self::PAID_EVENTS, self::REFUND_EVENTS, self::DISPUTE_EVENTS), true)) {
                PaymentNotificationService::admin('security.webhook', [], [
                    'reason' => 'A money-related webhook (' . $eventType . ') referenced an order/payment unknown to this application.',
                    'dedupe' => 'unknown:' . $eventId,
                ]);
            }
            return ['status' => 'ignored', 'message' => 'Unknown order', 'response' => ['ignored' => true]];
        }
        $pdo->prepare('UPDATE gateway_webhook_events SET purchase_id = :p WHERE id = :id')->execute([':p' => $purchase['id'], ':id' => $eventId]);
        $orderId = $orderId !== '' ? $orderId : (string) ($purchase['gateway_order_id'] ?? '');
        $paymentId = $paymentId !== '' ? $paymentId : (string) ($purchase['gateway_payment_id'] ?? '');

        if (in_array($eventType, self::REFUND_EVENTS, true)) {
            return self::handleRefund($purchase, $refund, $eventType);
        }
        if (in_array($eventType, self::DISPUTE_EVENTS, true)) {
            return self::handleDispute($purchase, $dispute, $eventType);
        }
        if (in_array($eventType, self::FAILED_EVENTS, true)) {
            return self::handleFailed($purchase, $payment, $eventType);
        }
        if (in_array($eventType, self::AUTHORIZED_EVENTS, true)) {
            return self::handleAuthorized($purchase, $payment, $orderId, $paymentId);
        }
        if (in_array($eventType, self::PAID_EVENTS, true)) {
            return self::handlePaid($purchase, $payment, $orderId, $paymentId, $eventType);
        }

        return ['status' => 'ignored', 'message' => 'Unhandled event type'];
    }

    private static function handlePaid(array $purchase, ?array $payment, string $orderId, string $paymentId, string $eventType): array
    {
        if (in_array((string) $purchase['payment_status'], ['refunded', 'disputed', 'cancelled'], true)) {
            AuditService::log((int) $purchase['user_id'], 'webhook.replay_blocked', 'purchase', (int) $purchase['id'], ['event' => $eventType], 'failure');
            return ['status' => 'ignored', 'message' => 'Purchase already resolved', 'response' => ['ignored' => true]];
        }

        // order.paid occasionally arrives without a payment entity — fetch it.
        if ($payment === null && $orderId !== '') {
            try {
                $client = new RazorpayClient();
                $payments = $client->fetchOrderPayments($orderId);
            } catch (\Throwable $e) {
                return ['status' => 'failed', 'message' => 'Gateway lookup failed'];
            }
            $captured = [];
            foreach ($payments as $p) {
                if (is_array($p) && ((string) ($p['status'] ?? '') === 'captured' || (int) ($p['captured'] ?? 0) === 1)) {
                    $captured[] = $p;
                }
            }
            if (count($captured) !== 1) {
                PaymentService::flagReview($purchase, $paymentId, 'Paid webhook without a single captured payment');
                PaymentNotificationService::event('payment.review', $purchase, [
                    'reason' => 'A paid event arrived without exactly one captured payment.',
                    'dedupe' => 'paid-lookup:' . $eventType . ':' . (string) $purchase['id'],
                ]);
                return ['status' => 'processed', 'message' => 'Needs review', 'response' => ['needs_review' => true]];
            }
            $payment = $captured[0];
        }
        if ($payment === null) {
            PaymentService::flagReview($purchase, $paymentId, 'Paid webhook without payment entity');
            return ['status' => 'processed', 'message' => 'Needs review', 'response' => ['needs_review' => true]];
        }

        $paymentId = trim((string) ($payment['id'] ?? $paymentId));
        if ($paymentId === '') {
            PaymentService::flagReview($purchase, '', 'Paid webhook without payment id');
            return ['status' => 'processed', 'message' => 'Needs review', 'response' => ['needs_review' => true]];
        }
        $res = PaymentService::verifyGatewayPayment($purchase, $payment, $orderId, $paymentId, 'webhook:' . $eventType);
        if (!empty($res['success'])) {
            return ['status' => 'processed', 'message' => null, 'response' => ['verified' => true]];
        }
        // Already-resolved replay is not an error.
        return ['status' => 'processed', 'message' => substr((string) ($res['error'] ?? 'Needs review'), 0, 255), 'response' => ['verified' => false, 'needs_review' => true]];
    }

    private static function handleAuthorized(array $purchase, ?array $payment, string $orderId, string $paymentId): array
    {
        if (in_array((string) $purchase['payment_status'], ['paid_verified', 'refunded', 'disputed', 'cancelled'], true)) {
            return ['status' => 'ignored', 'message' => 'Purchase already resolved'];
        }
        if ($payment === null || $paymentId === '') {
            return ['status' => 'processed', 'message' => 'Authorized without entity'];
        }
        if ((int) ($payment['captured'] ?? 0) === 1) {
            return self::handlePaid($purchase, $payment, $orderId !== '' ? $orderId : (string) ($payment['order_id'] ?? ''), $paymentId, 'payment.authorized');
        }
        PaymentService::flagReview($purchase, $paymentId, 'Payment authorized but not captured');
        PaymentNotificationService::event('payment.authorized', $purchase, ['dedupe' => $paymentId, 'payment_status' => 'needs_review']);
        return ['status' => 'processed', 'message' => 'Authorized awaiting capture', 'response' => ['authorized' => true]];
    }

    private static function handleFailed(array $purchase, ?array $payment, string $eventType): array
    {
        if (!in_array((string) $purchase['payment_status'], ['created', 'pending', 'failed'], true)) {
            // A failure after payment/refund is suspicious but must not downgrade state.
            if (!in_array((string) $purchase['payment_status'], ['failed', 'cancelled'], true)) {
                PaymentNotificationService::admin('payment.review', $purchase, [
                    'reason' => 'A failure event was received for a purchase that is already ' . (string) $purchase['payment_status'] . '.',
                    'dedupe' => 'late-fail:' . (string) $purchase['id'],
                ]);
            }
            return ['status' => 'ignored', 'message' => 'Failure ignored for current state'];
        }
        $errorCode = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($payment['error_code'] ?? '')) ?? '';
        $reason = $errorCode !== '' ? 'Gateway error code: ' . substr($errorCode, 0, 60) : 'The payment was not completed.';
        Connection::pdo()->prepare("UPDATE purchases SET payment_status='failed', failure_reason=:r WHERE id=:id AND payment_status IN ('created','pending')")
            ->execute([':r' => substr('Payment failed at gateway', 0, 255), ':id' => $purchase['id']]);
        AuditService::log((int) $purchase['user_id'], 'webhook.payment_failed', 'purchase', (int) $purchase['id'], ['event' => $eventType]);
        PaymentNotificationService::event('payment.failed', $purchase, [
            'reason' => $reason,
            'payment_status' => 'failed',
            'dedupe' => (string) ($payment['id'] ?? $eventType . ':' . $purchase['id']),
        ]);
        return ['status' => 'processed', 'message' => null, 'response' => ['failed' => true]];
    }

    private static function handleRefund(array $purchase, ?array $refund, string $eventType): array
    {
        if ($refund === null) {
            return ['status' => 'processed', 'message' => 'Refund event without entity'];
        }
        $refundId = trim((string) ($refund['id'] ?? ''));
        $amount = (int) ($refund['amount'] ?? 0);
        $statusRaw = strtolower((string) ($refund['status'] ?? ''));
        $refundCurrency = strtoupper(trim((string) ($refund['currency'] ?? '')));
        if ($refundId === '' || $amount <= 0 || $amount > (int) $purchase['amount_paise']
            || ($refundCurrency !== '' && $refundCurrency !== strtoupper((string) $purchase['currency']))
        ) {
            PaymentNotificationService::event('payment.review', $purchase, [
                'reason' => 'A refund event had a missing/invalid refund id or amount.',
                'dedupe' => 'refund-bad:' . substr($refundId, 0, 40),
            ]);
            return ['status' => 'processed', 'message' => 'Invalid refund payload'];
        }

        $map = ['processed' => 'processed', 'pending' => 'processing', 'created' => 'processing', 'failed' => 'failed', 'rejected' => 'rejected'];
        $status = $map[$statusRaw] ?? 'processing';
        if ($eventType === 'refund.failed') {
            $status = 'failed';
        } elseif ($eventType === 'refund.processed' && $status !== 'failed') {
            $status = 'processed';
        }

        self::upsertRefund($purchase, $refundId, $amount, $status, $statusRaw, $eventType);

        if ($status === 'processed') {
            Connection::pdo()->prepare("UPDATE purchases SET payment_status='refunded' WHERE id=:id AND payment_status IN ('paid_verified','needs_review','pending','disputed')")
                ->execute([':id' => $purchase['id']]);
            MembershipService::suspendForPurchase((int) $purchase['id'], 'Refund processed — pending review');
            AuditService::log((int) $purchase['user_id'], 'webhook.refund', 'purchase', (int) $purchase['id'], [
                'event' => $eventType, 'refund_id' => $refundId, 'amount' => $amount,
            ]);
            PaymentNotificationService::event('payment.refunded', $purchase, [
                'refund_amount_paise' => $amount,
                'payment_status' => 'refunded',
                'reason' => 'The payment gateway processed the refund.',
                'dedupe' => $refundId,
            ]);
            return ['status' => 'processed', 'message' => null, 'response' => ['refunded' => true]];
        }

        if ($status === 'failed' || $status === 'rejected') {
            AuditService::log((int) $purchase['user_id'], 'webhook.refund_failed', 'purchase', (int) $purchase['id'], [
                'event' => $eventType, 'refund_id' => $refundId,
            ]);
            PaymentNotificationService::event('payment.refund_failed', $purchase, [
                'refund_amount_paise' => $amount,
                'payment_status' => (string) $purchase['payment_status'],
                'reason' => 'The payment gateway could not complete the refund.',
                'dedupe' => $refundId,
            ]);
            return ['status' => 'processed', 'message' => 'Refund failed', 'response' => ['refund_failed' => true]];
        }

        PaymentNotificationService::event('refund.requested', $purchase, [
            'refund_amount_paise' => $amount,
            'reason' => 'A refund was initiated at the payment gateway.',
            'dedupe' => $refundId . ':requested',
        ]);
        return ['status' => 'processed', 'message' => 'Refund pending', 'response' => ['refund_pending' => true]];
    }

    private static function handleDispute(array $purchase, ?array $dispute, string $eventType): array
    {
        if ($dispute === null) {
            return ['status' => 'processed', 'message' => 'Dispute event without entity'];
        }
        $disputeId = trim((string) ($dispute['id'] ?? ''));
        if ($disputeId === '') {
            return ['status' => 'processed', 'message' => 'Dispute event without id'];
        }
        $status = strtolower((string) ($dispute['status'] ?? 'open'));
        $status = preg_replace('/[^a-z_]/', '', $status) ?? 'open';
        $amount = (int) ($dispute['amount'] ?? 0);
        $currency = strtoupper((string) ($dispute['currency'] ?? $purchase['currency']));
        if ($amount > (int) $purchase['amount_paise']) {
            $amount = (int) $purchase['amount_paise'];
        }
        if ($currency !== strtoupper((string) $purchase['currency'])) {
            PaymentNotificationService::event('payment.review', $purchase, [
                'reason' => 'A dispute currency did not match the purchase.',
                'dedupe' => 'dispute-currency:' . $disputeId,
            ]);
            return ['status' => 'processed', 'message' => 'Dispute currency mismatch'];
        }
        $reasonCode = substr((string) ($dispute['reason_code'] ?? $dispute['reason'] ?? ''), 0, 80);
        $paymentId = (string) ($dispute['payment_id'] ?? $purchase['gateway_payment_id'] ?? '');
        $openedAt = self::mysqlDate((string) ($dispute['created_at'] ?? ''));

        if (Schema::tableExists('payment_disputes')) {
            try {
                Connection::pdo()->prepare("INSERT INTO payment_disputes
                    (purchase_id, gateway_dispute_id, gateway_payment_id, amount_paise, currency, status, reason_code, opened_at)
                    VALUES (:pid,:did,:pay,:amt,:cur,:st,:rc,:op)
                    ON DUPLICATE KEY UPDATE status=VALUES(status), amount_paise=VALUES(amount_paise), reason_code=VALUES(reason_code), updated_at=UTC_TIMESTAMP()")
                    ->execute([
                        ':pid' => $purchase['id'], ':did' => substr($disputeId, 0, 80), ':pay' => substr($paymentId, 0, 80),
                        ':amt' => max(0, $amount), ':cur' => substr($currency, 0, 3), ':st' => substr($status, 0, 40),
                        ':rc' => $reasonCode, ':op' => $openedAt,
                    ]);
            } catch (\Throwable $e) {
                @error_log('WebhookService: dispute upsert failed (' . get_class($e) . ')');
            }
        }

        Connection::pdo()->prepare("UPDATE purchases SET payment_status='disputed' WHERE id=:id AND payment_status IN ('paid_verified','needs_review','pending')")
            ->execute([':id' => $purchase['id']]);
        MembershipService::suspendForPurchase((int) $purchase['id'], 'Payment dispute — pending review');
        AuditService::log((int) $purchase['user_id'], 'webhook.dispute', 'purchase', (int) $purchase['id'], [
            'event' => $eventType, 'dispute_id' => $disputeId, 'status' => $status,
        ]);
        PaymentNotificationService::event('payment.disputed', $purchase, [
            'dispute_status' => $status,
            'payment_status' => 'disputed',
            'reason' => $reasonCode !== '' ? $reasonCode : 'A dispute/chargeback was recorded by the gateway.',
            'dedupe' => $disputeId . ':' . $status,
        ]);
        return ['status' => 'processed', 'message' => null, 'response' => ['disputed' => true]];
    }

    private static function upsertRefund(array $purchase, string $refundId, int $amount, string $status, string $statusRaw, string $eventType): void
    {
        $pdo = Connection::pdo();
        $hasGatewayStatus = Schema::has('refunds', 'gateway_status');
        $hasProcessedAt = Schema::has('refunds', 'processed_at');
        $cols = ['purchase_id', 'gateway_refund_id', 'amount_paise', 'status', 'reason', 'requested_by'];
        $vals = [':pid', ':gid', ':amt', ':st', ':rs', ':rb'];
        $bind = [
            ':pid' => $purchase['id'], ':gid' => substr($refundId, 0, 80), ':amt' => $amount,
            ':st' => $status, ':rs' => substr('Gateway refund event: ' . $eventType, 0, 500), ':rb' => null,
        ];
        if ($hasGatewayStatus) {
            $cols[] = 'gateway_status';
            $vals[] = ':gs';
            $bind[':gs'] = substr($statusRaw, 0, 40);
        }
        if ($hasProcessedAt) {
            $cols[] = 'processed_at';
            $vals[] = ':pa';
            $bind[':pa'] = $status === 'processed' ? gmdate('Y-m-d H:i:s') : null;
        }
        $updates = 'status=VALUES(status), amount_paise=VALUES(amount_paise), updated_at=UTC_TIMESTAMP()';
        if ($hasGatewayStatus) {
            $updates .= ', gateway_status=VALUES(gateway_status)';
        }
        if ($hasProcessedAt) {
            $updates .= ', processed_at=VALUES(processed_at)';
        }
        try {
            $pdo->prepare('INSERT INTO refunds (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ') ON DUPLICATE KEY UPDATE ' . $updates)
                ->execute($bind);
        } catch (\Throwable $e) {
            @error_log('WebhookService: refund upsert failed (' . get_class($e) . ')');
        }
    }

    private static function findPurchase(\PDO $pdo, string $orderId, string $paymentId): ?array
    {
        if ($orderId !== '') {
            $s = $pdo->prepare('SELECT * FROM purchases WHERE gateway_order_id = :o LIMIT 1');
            $s->execute([':o' => $orderId]);
            $p = $s->fetch();
            if ($p) {
                return $p;
            }
        }
        if ($paymentId !== '') {
            $s = $pdo->prepare('SELECT * FROM purchases WHERE gateway_payment_id = :pay LIMIT 1');
            $s->execute([':pay' => $paymentId]);
            $p = $s->fetch();
            if ($p) {
                return $p;
            }
        }
        return null;
    }

    private static function findPurchaseByGatewayLookup(\PDO $pdo, array $entity, string $kind): ?array
    {
        $id = trim((string) ($entity['id'] ?? ''));
        if ($id === '') {
            return null;
        }
        try {
            $client = new RazorpayClient();
            $remote = $kind === 'refund' ? $client->fetchRefund($id) : $client->fetchDispute($id);
            $paymentId = trim((string) ($remote['payment_id'] ?? ''));
            return $paymentId !== '' ? self::findPurchase($pdo, '', $paymentId) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function summary(array $event): array
    {
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
        $kind = '';
        $entity = null;
        foreach (['payment', 'order', 'refund', 'dispute'] as $key) {
            if (isset($payload[$key]['entity']) && is_array($payload[$key]['entity'])) {
                $kind = $key;
                $entity = $payload[$key]['entity'];
                break;
            }
        }
        $entity = is_array($entity) ? $entity : [];
        $orderId = (string) ($entity['order_id'] ?? '');
        if ($orderId === '' && $kind === 'order') {
            $orderId = (string) ($entity['id'] ?? '');
        }
        $paymentId = (string) ($entity['payment_id'] ?? '');
        if ($paymentId === '' && $kind === 'payment') {
            $paymentId = (string) ($entity['id'] ?? '');
        }
        return [
            'event' => substr((string) ($event['event'] ?? ''), 0, 80),
            'order_id' => substr($orderId, 0, 80),
            'payment_id' => substr($paymentId, 0, 80),
            'refund_id' => $kind === 'refund' ? substr((string) ($entity['id'] ?? ''), 0, 80) : '',
            'dispute_id' => $kind === 'dispute' ? substr((string) ($entity['id'] ?? ''), 0, 80) : '',
            'status' => substr((string) ($entity['status'] ?? ''), 0, 40),
            'amount' => isset($entity['amount']) ? (int) $entity['amount'] : null,
            'currency' => substr((string) ($entity['currency'] ?? ''), 0, 3),
        ];
    }

    private static function finish(\PDO $pdo, int $eventId, string $status, ?string $message): void
    {
        try {
            $pdo->prepare("UPDATE gateway_webhook_events SET processing_status=:s, processed_at=UTC_TIMESTAMP(), error_message=:m WHERE id=:id")
                ->execute([':s' => $status, ':m' => $message !== null ? substr($message, 0, 255) : null, ':id' => $eventId]);
        } catch (\Throwable $e) {
            @error_log('WebhookService: could not finalise event (' . get_class($e) . ')');
        }
    }

    private static function mysqlDate(string $unix): ?string
    {
        if ($unix === '' || !ctype_digit($unix)) {
            return null;
        }
        $ts = (int) $unix;
        return $ts > 0 ? gmdate('Y-m-d H:i:s', $ts) : null;
    }

    private static function isDuplicate(\PDOException $e): bool
    {
        $info = $e->errorInfo ?? [];
        return (($info[0] ?? '') === '23000' && (int) ($info[1] ?? 0) === 1062)
            || str_contains($e->getMessage(), 'Duplicate entry');
    }
}
