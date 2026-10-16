<?php
declare(strict_types=1);

namespace Matrimony\Services;

use Matrimony\Database\Connection;
use Matrimony\Security\AuditService;

/**
 * Razorpay webhook processing: raw-body signature, idempotency,
 * amount/currency validation. Never activates membership.
 */
final class WebhookService
{
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
        $eventType = (string) ($event['event'] ?? '');
        // Deterministic idempotency key: provider id or hash
        $providerKey = (string) ($event['id'] ?? '');
        if ($providerKey === '') {
            $providerKey = 'evt_' . hash('sha256', $rawBody);
        }
        $payload = $event['payload']['payment']['entity'] ?? $event['payload']['order']['entity'] ?? $event['payload'] ?? [];
        $orderId = (string) ($payload['order_id'] ?? '');
        $paymentId = (string) ($payload['id'] ?? '');
        if (str_starts_with($orderId, 'order_') === false && $paymentId !== '') {
            // payment.captured without order nesting — try lookup by payment
        }
        $pdo = Connection::pdo();
        $eventId = 0;
        // Idempotency: unique (gateway, provider_event_key). On duplicate, resume if never finished.
        try {
            $pdo->prepare("INSERT INTO gateway_webhook_events (gateway, provider_event_key, event_type, payload_hash, payload_redacted, processing_status) VALUES ('razorpay',:k,:t,:h,:p,'received')")
                ->execute([
                    ':k' => substr($providerKey, 0, 120), ':t' => substr($eventType, 0, 80),
                    ':h' => hash('sha256', $rawBody),
                    ':p' => substr(json_encode(AuditService::redact(is_array($payload) ? $payload : [])), 0, 4000),
                ]);
            $eventId = (int) $pdo->lastInsertId();
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                http_response_code(500);
                return ['ok' => false, 'error' => 'Could not record event'];
            }
            $s = $pdo->prepare("SELECT id, processing_status FROM gateway_webhook_events WHERE gateway = 'razorpay' AND provider_event_key = :k LIMIT 1");
            $s->execute([':k' => substr($providerKey, 0, 120)]);
            $prev = $s->fetch();
            if (!$prev) {
                http_response_code(200);
                return ['ok' => true, 'duplicate' => true];
            }
            $prevStatus = (string) ($prev['processing_status'] ?? '');
            // Only short-circuit when already finished; resume received/failed so paid events are not dropped.
            if (in_array($prevStatus, ['processed', 'ignored'], true)) {
                http_response_code(200);
                return ['ok' => true, 'duplicate' => true];
            }
            $eventId = (int) $prev['id'];
            $pdo->prepare("UPDATE gateway_webhook_events SET processing_status = 'received', error_message = NULL, event_type = :t, payload_hash = :h WHERE id = :id")
                ->execute([
                    ':t' => substr($eventType, 0, 80),
                    ':h' => hash('sha256', $rawBody),
                    ':id' => $eventId,
                ]);
        }
        // Match provider order to exactly one local purchase
        $purchase = null;
        if ($orderId !== '') {
            $s = $pdo->prepare('SELECT * FROM purchases WHERE gateway_order_id = :o LIMIT 1');
            $s->execute([':o' => $orderId]);
            $purchase = $s->fetch();
        }
        if (!$purchase && $paymentId !== '') {
            $s = $pdo->prepare('SELECT * FROM purchases WHERE gateway_payment_id = :pay LIMIT 1');
            $s->execute([':pay' => $paymentId]);
            $purchase = $s->fetch();
        }
        if (!$purchase) {
            $pdo->prepare("UPDATE gateway_webhook_events SET processing_status='ignored', error_message='unknown order', processed_at=UTC_TIMESTAMP() WHERE id=:id")->execute([':id' => $eventId]);
            AuditService::log(null, 'webhook.unknown_order', 'webhook', $eventId, ['order' => substr($orderId, 0, 40)], 'failure');
            http_response_code(200); // ack to stop retries; ops review via ignored list
            return ['ok' => true, 'ignored' => true];
        }
        $pdo->prepare('UPDATE gateway_webhook_events SET purchase_id = :p WHERE id = :id')->execute([':p' => $purchase['id'], ':id' => $eventId]);
        // Amount/currency validation
        $gwAmount = isset($payload['amount']) ? (int) $payload['amount'] : null;
        $gwCurrency = strtoupper((string) ($payload['currency'] ?? ''));
        if ($gwAmount !== null && ($gwAmount !== (int) $purchase['amount_paise'] || ($gwCurrency !== '' && $gwCurrency !== strtoupper($purchase['currency'])))) {
            $pdo->prepare("UPDATE purchases SET payment_status='needs_review', failure_reason='Amount/currency mismatch via webhook' WHERE id=:id")->execute([':id' => $purchase['id']]);
            $pdo->prepare("UPDATE gateway_webhook_events SET processing_status='processed', processed_at=UTC_TIMESTAMP(), error_message='amount mismatch' WHERE id=:id")->execute([':id' => $eventId]);
            AuditService::log((int) $purchase['user_id'], 'webhook.amount_mismatch', 'purchase', $purchase['id'], ['expected' => $purchase['amount_paise'], 'got' => $gwAmount], 'failure');
            NotificationService::notifyAdmins('payment_review', 'Amount mismatch', 'Purchase ' . $purchase['public_reference'] . ' amount mismatch. Needs review.', 'purchase', $purchase['id']);
            http_response_code(200);
            return ['ok' => true, 'needs_review' => true];
        }
        // Only captured/paid events count as money received. `payment.authorized`
        // means funds are merely held (manual capture), so it must NOT entitle.
        $paidEvents = ['payment.captured', 'order.paid'];
        $failEvents = ['payment.failed'];
        $refundEvents = ['payment.refunded', 'refund.created', 'refund.processed'];
        $disputeEvents = ['payment.disputed', 'dispute.created'];
        if (in_array($eventType, $paidEvents, true)) {
            $res = PaymentService::markPaidVerified($purchase, $paymentId ?: (string) ($purchase['gateway_payment_id'] ?? ''), 'webhook:' . $eventType);
            $pdo->prepare("UPDATE gateway_webhook_events SET processing_status='processed', processed_at=UTC_TIMESTAMP() WHERE id=:id")->execute([':id' => $eventId]);
            http_response_code(200);
            return ['ok' => true, 'verified' => $res['success']];
        }
        if (in_array($eventType, $failEvents, true)) {
            $pdo->prepare("UPDATE purchases SET payment_status='failed', failure_reason=:r WHERE id=:id AND payment_status IN ('created','pending')")->execute([':r' => substr('Gateway: ' . $eventType, 0, 255), ':id' => $purchase['id']]);
            $pdo->prepare("UPDATE gateway_webhook_events SET processing_status='processed', processed_at=UTC_TIMESTAMP() WHERE id=:id")->execute([':id' => $eventId]);
            http_response_code(200);
            return ['ok' => true, 'failed' => true];
        }
        if (in_array($eventType, $refundEvents, true)) {
            $pdo->prepare("UPDATE purchases SET payment_status='refunded' WHERE id=:id AND payment_status IN ('paid_verified')")->execute([':id' => $purchase['id']]);
            // Flag membership for review per policy
            $pdo->prepare("UPDATE memberships SET status='suspended', suspension_reason='Refund/chargeback — pending review' WHERE purchase_id=:pid AND status='active'")->execute([':pid' => $purchase['id']]);
            $pdo->prepare("UPDATE gateway_webhook_events SET processing_status='processed', processed_at=UTC_TIMESTAMP() WHERE id=:id")->execute([':id' => $eventId]);
            AuditService::log((int) $purchase['user_id'], 'webhook.refund', 'purchase', $purchase['id'], ['event' => $eventType]);
            NotificationService::notifyAdmins('refund', 'Refund event', 'Purchase ' . $purchase['public_reference'] . ' refund event: ' . $eventType, 'purchase', $purchase['id']);
            http_response_code(200);
            return ['ok' => true, 'refunded' => true];
        }
        if (in_array($eventType, $disputeEvents, true)) {
            $pdo->prepare("UPDATE purchases SET payment_status='disputed' WHERE id=:id AND payment_status IN ('paid_verified','created','pending')")->execute([':id' => $purchase['id']]);
            // A chargeback/dispute must also suspend any active membership, like a refund.
            $pdo->prepare("UPDATE memberships SET status='suspended', suspension_reason='Payment dispute — pending review' WHERE purchase_id=:pid AND status='active'")->execute([':pid' => $purchase['id']]);
            $pdo->prepare("UPDATE gateway_webhook_events SET processing_status='processed', processed_at=UTC_TIMESTAMP() WHERE id=:id")->execute([':id' => $eventId]);
            AuditService::log((int) $purchase['user_id'], 'webhook.dispute', 'purchase', $purchase['id'], ['event' => $eventType]);
            NotificationService::notifyAdmins('dispute', 'Dispute event', 'Purchase ' . $purchase['public_reference'] . ' dispute: ' . $eventType, 'purchase', $purchase['id']);
            http_response_code(200);
            return ['ok' => true, 'disputed' => true];
        }
        $pdo->prepare("UPDATE gateway_webhook_events SET processing_status='ignored', processed_at=UTC_TIMESTAMP() WHERE id=:id")->execute([':id' => $eventId]);
        http_response_code(200);
        return ['ok' => true, 'ignored' => true];
    }
}
