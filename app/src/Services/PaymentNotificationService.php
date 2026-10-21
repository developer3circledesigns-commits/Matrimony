<?php
declare(strict_types=1);

namespace Matrimony\Services;

/**
 * Single place that maps payment lifecycle events to user + admin emails.
 *
 * Email delivery is queued through EmailOutboxService, so a slow/down SMTP
 * server can never roll back a verified payment or a membership change.
 */
final class PaymentNotificationService
{
    /** event => [user template|null, admin template|null] */
    private const MAP = [
        'payment.started' => ['payment_started', 'admin_payment_started'],
        'payment.authorized' => ['payment_authorized', 'admin_payment_authorized'],
        'payment.paid' => ['payment_received_pending', 'admin_activation_needed'],
        'payment.failed' => ['payment_failed', 'admin_payment_failed'],
        'payment.review' => ['payment_needs_review', 'admin_payment_review'],
        'payment.refunded' => ['payment_refunded', 'admin_refund'],
        'payment.refund_failed' => ['payment_refund_failed', 'admin_refund_failed'],
        'payment.disputed' => ['payment_disputed', 'admin_dispute'],
        'refund.requested' => ['refund_requested', 'admin_refund_requested'],
        'activation.hold' => ['activation_on_hold', 'admin_activation_hold'],
        'activation.reject' => ['activation_rejected', 'admin_activation_reject'],
        'membership.activated' => ['membership_activated', 'admin_membership_activated'],
        'membership.suspended' => ['membership_suspended', 'admin_membership_suspended'],
        'gateway.error' => ['payment_gateway_error', 'admin_gateway_error'],
        'security.webhook' => [null, 'admin_webhook_alert'],
    ];

    /**
     * Queue both the member and admin notifications for an event.
     * @param array $purchase purchases row (or compatible array)
     * @param array $ctx event-specific context (reason, refund amount, ids, dedupe)
     */
    public static function event(string $event, array $purchase, array $ctx = []): void
    {
        [$userTemplate, $adminTemplate] = self::MAP[$event] ?? [null, null];
        if ($userTemplate === null && $adminTemplate === null) {
            return;
        }
        $vars = self::vars($event, $purchase, $ctx);
        $opts = self::opts($purchase, $event, $ctx);

        if ($userTemplate !== null) {
            $userId = (int) ($purchase['user_id'] ?? 0);
            if ($userId > 0) {
                EmailOutboxService::queue($userId, $userTemplate, $vars, null, $opts);
            }
        }
        if ($adminTemplate !== null) {
            EmailOutboxService::queueToAdmins($adminTemplate, $vars, $opts);
        }
    }

    /** Queue only the member email (rarely needed). */
    public static function user(string $event, array $purchase, array $ctx = []): void
    {
        [$userTemplate] = self::MAP[$event] ?? [null, null];
        if ($userTemplate === null) {
            return;
        }
        $userId = (int) ($purchase['user_id'] ?? 0);
        if ($userId > 0) {
            EmailOutboxService::queue($userId, $userTemplate, self::vars($event, $purchase, $ctx), null, self::opts($purchase, $event, $ctx));
        }
    }

    /** Queue only the admin email (security/ops alerts). */
    public static function admin(string $event, array $purchase = [], array $ctx = []): void
    {
        [, $adminTemplate] = self::MAP[$event] ?? [null, null];
        if ($adminTemplate === null) {
            return;
        }
        EmailOutboxService::queueToAdmins($adminTemplate, self::vars($event, $purchase, $ctx), self::opts($purchase, $event, $ctx));
    }

    private static function vars(string $event, array $purchase, array $ctx): array
    {
        $vars = array_merge($purchase, $ctx);
        $vars['event'] = $event;
        $vars['event_label'] = self::label($event);
        $vars['support_url'] = function_exists('url') ? url('/contact') : '';
        if (!isset($vars['occurred_at'])) {
            $vars['occurred_at'] = gmdate('d M Y, H:i') . ' UTC';
        }
        return $vars;
    }

    private static function opts(array $purchase, string $event, array $ctx): array
    {
        $opts = [
            'related_type' => 'purchase',
            'related_id' => isset($purchase['id']) ? (int) $purchase['id'] : null,
        ];
        // Dedupe on provider ids where available. Null means "no dedupe".
        $unique = (string) ($ctx['dedupe'] ?? '');
        if ($unique !== '') {
            $pid = (string) ($purchase['id'] ?? '0');
            $opts['dedupe_key'] = substr(hash('sha256', $event . '|' . $pid . '|' . $unique), 0, 64);
        }
        return $opts;
    }

    private static function label(string $event): string
    {
        return [
            'payment.started' => 'Payment started',
            'payment.authorized' => 'Payment authorized',
            'payment.paid' => 'Payment received',
            'payment.failed' => 'Payment failed',
            'payment.review' => 'Payment under review',
            'payment.refunded' => 'Refund processed',
            'payment.refund_failed' => 'Refund failed',
            'payment.disputed' => 'Payment dispute',
            'refund.requested' => 'Refund requested',
            'activation.hold' => 'Activation on hold',
            'activation.reject' => 'Activation rejected',
            'membership.activated' => 'Membership activated',
            'membership.suspended' => 'Membership suspended',
            'gateway.error' => 'Payment gateway error',
            'security.webhook' => 'Webhook security alert',
        ][$event] ?? $event;
    }
}
