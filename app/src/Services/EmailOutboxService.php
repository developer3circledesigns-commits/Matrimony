<?php
declare(strict_types=1);

namespace Matrimony\Services;

use Matrimony\Database\Connection;
use Matrimony\Database\Schema;

/**
 * Queue emails AFTER committing business state; cron retries delivery.
 * SMTP failure must never roll back a verified payment/activation.
 */
final class EmailOutboxService
{
    /** @param array{related_type?:string,related_id?:int|null,dedupe_key?:string} $opts */
    public static function queue(int $userId, string $templateKey, array $vars = [], ?string $toEmail = null, array $opts = []): void
    {
        try {
            $pdo = Connection::pdo();
            if ($toEmail === null && $userId > 0) {
                $s = $pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
                $s->execute([':id' => $userId]);
                $toEmail = (string) $s->fetchColumn();
            }
            $toEmail = trim((string) $toEmail);
            if ($toEmail === '' || strlen($toEmail) > 190 || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            [$subject, $html, $text] = self::render($templateKey, $vars);
            $subject = function_exists('mb_substr') ? mb_substr($subject, 0, 200) : substr($subject, 0, 200);
            $templateKey = substr($templateKey, 0, 80);

            $cols = ['recipient_email', 'recipient_user_id', 'template_key', 'subject', 'body_html', 'body_text'];
            $vals = [':e', ':u', ':k', ':s', ':h', ':t'];
            $bind = [
                ':e' => $toEmail,
                ':u' => $userId > 0 ? $userId : null,
                ':k' => $templateKey,
                ':s' => $subject,
                ':h' => $html,
                ':t' => $text,
            ];
            if (Schema::has('email_outbox', 'related_type')) {
                $cols[] = 'related_type';
                $vals[] = ':rt';
                $bind[':rt'] = isset($opts['related_type']) ? substr((string) $opts['related_type'], 0, 40) : null;
            }
            if (Schema::has('email_outbox', 'related_id')) {
                $cols[] = 'related_id';
                $vals[] = ':ri';
                $bind[':ri'] = !empty($opts['related_id']) ? (int) $opts['related_id'] : null;
            }
            $dedupe = isset($opts['dedupe_key']) ? (string) $opts['dedupe_key'] : '';
            // Dedupe must be per-recipient: the unique index is global, and the
            // same event queues both a member email and one or more admin emails.
            if ($dedupe !== '') {
                $dedupe = hash('sha256', $dedupe . '|' . strtolower($toEmail));
            }
            $hasDedupe = Schema::has('email_outbox', 'dedupe_key');
            if ($hasDedupe) {
                $cols[] = 'dedupe_key';
                $vals[] = ':dk';
                $bind[':dk'] = $dedupe !== '' ? $dedupe : null;
            }
            $verb = ($hasDedupe && $dedupe !== '') ? 'INSERT IGNORE' : 'INSERT';
            $sql = $verb . ' INTO email_outbox (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')';
            $pdo->prepare($sql)->execute($bind);
        } catch (\Throwable $e) {
            @error_log('EmailOutboxService::queue failed: ' . substr($e->getMessage(), 0, 300));
        }
    }

    /** @param array{related_type?:string,related_id?:int|null,dedupe_key?:string} $opts */
    public static function queueToAdmins(string $templateKey, array $vars = [], array $opts = []): void
    {
        try {
            $pdo = Connection::pdo();
            $emails = [];

            // Dedicated operations inbox first (may not be an app user).
            $paymentCfg = require BASE_PATH . '/app/config/payments.php';
            $adminEmail = trim((string) (getenv('ADMIN_EMAIL') ?: ($paymentCfg['admin_email'] ?? '')));
            if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $emails[strtolower($adminEmail)] = $adminEmail;
            }

            $rows = $pdo->query("SELECT id, email FROM users WHERE role IN ('admin','super_admin') AND account_status = 'active'")->fetchAll();
            foreach ($rows as $r) {
                $email = trim((string) ($r['email'] ?? ''));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[strtolower($email)] = $email;
                }
            }

            foreach ($emails as $email) {
                self::queue(0, $templateKey, $vars, $email, $opts);
            }
        } catch (\Throwable $e) {
            @error_log('EmailOutboxService::queueToAdmins failed: ' . substr($e->getMessage(), 0, 300));
        }
    }

    /** @return array{string,string,string} subject, html, text */
    public static function render(string $key, array $v): array
    {
        $e = fn($x) => htmlspecialchars((string) ($x ?? ''), ENT_QUOTES, 'UTF-8');
        $plan = $e($v['plan_name_snapshot'] ?? $v['plan'] ?? 'your plan');
        $ref = $e($v['public_reference'] ?? $v['reference'] ?? '');
        $amt = isset($v['amount_paise']) ? PlanService::formatINR((int) $v['amount_paise']) : $e($v['amount'] ?? '');
        $refundAmt = isset($v['refund_amount_paise']) ? PlanService::formatINR((int) $v['refund_amount_paise']) : '';
        $app = $e(getenv('APP_NAME') ?: 'Matrimony');
        $site = function_exists('url') ? url() : (string) (getenv('APP_URL') ?: '');
        $support = function_exists('url') ? url('/contact') : $site;
        $reason = trim((string) ($v['reason'] ?? $v['decision_reason'] ?? $v['message'] ?? ''));
        $reasonText = $reason !== '' ? $e($reason) : 'No additional detail was provided.';
        $status = $e($v['payment_status'] ?? $v['status'] ?? '');
        $eventLabel = $e($v['event_label'] ?? 'Payment update');
        $paymentId = $e($v['gateway_payment_id'] ?? '');
        $occurred = $e($v['occurred_at'] ?? gmdate('d M Y, H:i') . ' UTC');

        $row = static function (string $label, string $value) use ($e): string {
            if ($value === '') {
                return '';
            }
            return '<tr><td style="padding:6px 12px 6px 0;color:#666;white-space:nowrap">' . $e($label)
                . '</td><td style="padding:6px 0"><b>' . $e($value) . '</b></td></tr>';
        };
        $details = '<table style="border-collapse:collapse;margin:12px 0;font-size:14px">'
            . $row('Reference', html_entity_decode($ref, ENT_QUOTES, 'UTF-8'))
            . $row('Plan', html_entity_decode($plan, ENT_QUOTES, 'UTF-8'))
            . ($amt !== '' ? $row('Amount', html_entity_decode($amt, ENT_QUOTES, 'UTF-8')) : '')
            . ($refundAmt !== '' ? $row('Refund', html_entity_decode($refundAmt, ENT_QUOTES, 'UTF-8')) : '')
            . ($status !== '' ? $row('Status', html_entity_decode($status, ENT_QUOTES, 'UTF-8')) : '')
            . ($paymentId !== '' ? $row('Payment ID', html_entity_decode($paymentId, ENT_QUOTES, 'UTF-8')) : '')
            . $row('Time', html_entity_decode($occurred, ENT_QUOTES, 'UTF-8'))
            . '</table>';
        $footer = '<hr style="border:none;border-top:1px solid #eee;margin:18px 0">'
            . '<p style="color:#888;font-size:12px">Need help? <a href="' . $e($support) . '">Contact support</a>'
            . ($site !== '' ? ' &middot; <a href="' . $e($site) . '">' . $app . '</a>' : '') . '</p>';
        $wrap = static function (string $heading, string $intro, string $extra = '') use ($details, $footer): string {
            return '<div style="font-family:Arial,sans-serif;line-height:1.55;color:#222;max-width:620px">'
                . '<h2 style="font-size:18px;margin:0 0 10px">' . $heading . '</h2>'
                . '<p>' . $intro . '</p>' . $details . $extra . $footer . '</div>';
        };

        switch ($key) {
            case 'payment_started':
                $s = "Payment started — $plan [$ref]";
                $b = $wrap('Complete your payment', 'We created a secure payment order for <b>' . $plan . '</b>. Your plan is not active until the payment is verified.', '<p>If you did not start this payment, ignore this email or contact support.</p>');
                break;
            case 'payment_authorized':
                $s = "Payment authorized — awaiting capture [$ref]";
                $b = $wrap('Payment authorized', 'Your payment of <b>' . $amt . '</b> is authorized but has not been captured yet. Our team has been notified and will confirm it shortly.', '<p>No plan is activated until capture is confirmed.</p>');
                break;
            case 'payment_received_pending':
                $s = "Payment received — awaiting activation [$ref]";
                $b = $wrap('Payment received', 'We received your payment of <b>' . $amt . '</b> for <b>' . $plan . '</b>. It is verified and awaiting admin activation. We will email you once it is active.', '<p>You can track it from your <a href="' . $e($site) . '/payment/history">payment history</a>.</p>');
                break;
            case 'payment_failed':
                $s = "Payment failed — $plan [$ref]";
                $b = $wrap('Payment failed', 'Your payment of <b>' . $amt . '</b> for <b>' . $plan . '</b> did not complete. No plan was activated and no membership allowance was used.', '<p>You can retry from the <a href="' . $e($site) . '/plans">plans page</a>. If money left your account, contact support with the reference above.</p>');
                break;
            case 'payment_needs_review':
                $s = "Payment needs review [$ref]";
                $b = $wrap('Payment under review', 'Your payment (ref <b>' . $ref . '</b>) needs a quick manual review. Our team has been notified.', '<p><b>Reason:</b> ' . $reasonText . '</p><p>We will email you once the review is complete.</p>');
                break;
            case 'payment_refunded':
                $s = "Refund processed [$ref]";
                $b = $wrap('Refund processed', 'A refund of <b>' . ($refundAmt !== '' ? $refundAmt : $amt) . '</b> has been processed for <b>' . $plan . '</b> (ref <b>' . $ref . '</b>).', '<p>Any membership linked to this payment has been suspended pending review.</p>');
                break;
            case 'payment_refund_failed':
                $s = "Refund could not be completed [$ref]";
                $b = $wrap('Refund failed', 'We could not complete the refund for <b>' . $ref . '</b>. Our team is looking into it.', '<p><b>Reason:</b> ' . $reasonText . '</p>');
                break;
            case 'payment_disputed':
                $s = "Payment dispute update [$ref]";
                $b = $wrap('Payment dispute', 'A dispute/chargeback was recorded for <b>' . $ref . '</b> (' . $plan . ').', '<p><b>Status:</b> ' . $status . '</p><p><b>Reason:</b> ' . $reasonText . '</p><p>Your membership is suspended while the dispute is reviewed.</p>');
                break;
            case 'refund_requested':
                $s = "Refund request received [$ref]";
                $b = $wrap('Refund request received', 'We recorded a refund request of <b>' . ($refundAmt !== '' ? $refundAmt : $amt) . '</b> for <b>' . $plan . '</b> (ref <b>' . $ref . '</b>).', '<p><b>Reason:</b> ' . $reasonText . '</p><p>Refunds are processed by the payment gateway and can take several business days.</p>');
                break;
            case 'activation_on_hold':
                $s = "Activation on hold [$ref]";
                $b = $wrap('Activation on hold', 'Your activation request for <b>' . $plan . '</b> (ref <b>' . $ref . '</b>) is on hold.', '<p><b>Reason:</b> ' . $reasonText . '</p><p>Your payment remains recorded. Contact support if you need help.</p>');
                break;
            case 'activation_rejected':
                $s = "Activation request rejected [$ref]";
                $b = $wrap('Activation request rejected', 'Your activation request for <b>' . $plan . '</b> (ref <b>' . $ref . '</b>) was rejected.', '<p><b>Reason:</b> ' . $reasonText . '</p><p>If you believe this is a mistake, contact support with your payment reference.</p>');
                break;
            case 'membership_activated':
                $s = "Your $plan membership is active";
                $b = $wrap('Membership activated', 'Your <b>' . $plan . '</b> membership is now active. Amount paid: <b>' . $amt . '</b>.', '<p>Manage your plan from <a href="' . $e($site) . '/membership">My membership</a>.</p>');
                break;
            case 'membership_suspended':
                $s = "Membership suspended";
                $b = $wrap('Membership suspended', 'Your membership status changed to <b>suspended</b>.', '<p><b>Reason:</b> ' . $reasonText . '</p><p>Contact support if you need help.</p>');
                break;
            case 'payment_gateway_error':
                $s = "Payment could not be completed [$ref]";
                $b = $wrap('Payment could not be completed', 'We could not reach the payment gateway for <b>' . $plan . '</b> (ref <b>' . $ref . '</b>).', '<p>No money was captured. Please try again from the <a href="' . $e($site) . '/plans">plans page</a>.</p><p><b>Detail:</b> ' . $reasonText . '</p>');
                break;

            case 'admin_payment_started':
                $s = "New payment started [$ref]";
                $b = $wrap('New payment started', 'A member started checkout for <b>' . $plan . '</b> (<b>' . $amt . '</b>).', '<p>No action is needed unless the payment remains incomplete.</p>');
                break;
            case 'admin_payment_authorized':
                $s = "Capture required [$ref]";
                $b = $wrap('Payment authorized — capture required', 'Payment <b>' . $amt . '</b> for <b>' . $plan . '</b> is authorized but not captured.', '<p>Capture it in the Razorpay dashboard or via reconciliation.</p>');
                break;
            case 'admin_activation_needed':
                $s = "Action needed: verified payment awaiting activation [$ref]";
                $b = $wrap('Verified payment awaiting activation', 'A payment of <b>' . $amt . '</b> for <b>' . $plan . '</b> is verified and awaiting activation.', '<p>Review it in the <a href="' . $e($site) . '/admin/payments?status=awaiting_admin">activation queue</a>.</p>');
                break;
            case 'admin_payment_failed':
                $s = "Payment failed [$ref]";
                $b = $wrap('Payment failed', 'A member payment of <b>' . $amt . '</b> for <b>' . $plan . '</b> failed.', '<p><b>Reason:</b> ' . $reasonText . '</p>');
                break;
            case 'admin_payment_review':
                $s = "Payment needs review [$ref]";
                $b = $wrap('Payment needs review', 'Purchase <b>' . $ref . '</b> (' . $plan . ', <b>' . $amt . '</b>) needs manual review.', '<p><b>Reason:</b> ' . $reasonText . '</p><p>Review it in <a href="' . $e($site) . '/admin/payments?status=needs_review">needs review</a>.</p>');
                break;
            case 'admin_refund':
                $s = "Refund processed [$ref]";
                $b = $wrap('Refund processed', 'A refund of <b>' . ($refundAmt !== '' ? $refundAmt : $amt) . '</b> was processed for <b>' . $ref . '</b> (' . $plan . ').', '<p>Any active membership was suspended for review.</p>');
                break;
            case 'admin_refund_failed':
                $s = "Refund failed [$ref]";
                $b = $wrap('Refund failed', 'The refund for <b>' . $ref . '</b> failed.', '<p><b>Reason:</b> ' . $reasonText . '</p>');
                break;
            case 'admin_dispute':
                $s = "Dispute event [$ref]";
                $b = $wrap('Dispute/chargeback event', 'Purchase <b>' . $ref . '</b> has a dispute update.', '<p><b>Status:</b> ' . $status . '</p><p><b>Reason:</b> ' . $reasonText . '</p><p>The linked membership was suspended for review.</p>');
                break;
            case 'admin_refund_requested':
                $s = "Refund requested [$ref]";
                $b = $wrap('Refund requested', 'An admin recorded a refund request of <b>' . ($refundAmt !== '' ? $refundAmt : $amt) . '</b> for <b>' . $ref . '</b>.', '<p><b>Reason:</b> ' . $reasonText . '</p><p>Process it in the Razorpay dashboard; the webhook will settle the state.</p>');
                break;
            case 'admin_activation_hold':
                $s = "Activation placed on hold [$ref]";
                $b = $wrap('Activation on hold', 'Activation for <b>' . $ref . '</b> was placed on hold.', '<p><b>Reason:</b> ' . $reasonText . '</p>');
                break;
            case 'admin_activation_reject':
                $s = "Activation rejected [$ref]";
                $b = $wrap('Activation rejected', 'Activation for <b>' . $ref . '</b> was rejected.', '<p><b>Reason:</b> ' . $reasonText . '</p>');
                break;
            case 'admin_membership_activated':
                $s = "Membership activated [$ref]";
                $b = $wrap('Membership activated', '<b>' . $plan . '</b> was activated for purchase <b>' . $ref . '</b>.', '');
                break;
            case 'admin_membership_suspended':
                $s = "Membership suspended";
                $b = $wrap('Membership suspended', 'A membership was suspended.', '<p><b>Reason:</b> ' . $reasonText . '</p>');
                break;
            case 'admin_gateway_error':
                $s = "Payment gateway error [$ref]";
                $b = $wrap('Payment gateway error', 'A gateway request failed for purchase <b>' . $ref . '</b> (' . $plan . ').', '<p><b>Detail:</b> ' . $reasonText . '</p><p>No entitlement was granted. Check the gateway dashboard and application logs.</p>');
                break;
            case 'admin_webhook_alert':
                $s = "Security alert: Razorpay webhook";
                $b = $wrap('Razorpay webhook alert', 'A webhook could not be processed.', '<p><b>Detail:</b> ' . $reasonText . '</p><p>Payment state was not changed. Investigate the webhook secret and payload.</p>');
                break;

            case 'account_created':
                $s = "$app account created";
                $b = $wrap('Your account is ready', 'An administrator created a Matrimony account for you. Sign in with the email address you were given.', '<p><a href="' . $e($site) . '/users/login">Sign in</a></p>');
                break;
            case 'password_reset':
                $link = $e($v['link'] ?? '');
                $s = "Reset your password";
                $b = $wrap('Reset your password', 'We received a request to reset your password. This link expires in 1 hour and can be used once.', '<p><a href="' . $link . '">Choose a new password</a></p><p style="color:#888;font-size:12px">If you did not request this, you can safely ignore this email.</p>');
                break;
            case 'contact_inquiry':
                $s = (string) ($v['subject_line'] ?? ("[$app] Contact form message"));
                $b = '<div style="font-family:Arial,sans-serif;line-height:1.5;color:#222">'
                    . (string) ($v['body_html'] ?? ('<p>' . $e($v['message'] ?? '') . '</p>')) . '</div>';
                break;
            default:
                $s = "$app notification";
                $b = $wrap($e($eventLabel), $e($v['message'] ?? 'You have a new notification.'), '');
                break;
        }

        return [$s, $b, trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</tr>'], "\n", $b)), ENT_QUOTES, 'UTF-8'))];
    }

    /**
     * Send due messages (used by cron + admin retry).
     * @return array{int,int} [sent, failed]
     */
    public static function processDue(int $limit = 25, ?int $messageId = null): array
    {
        $sent = 0;
        $failed = 0;
        $limit = max(1, min(100, $limit));
        try {
            $pdo = Connection::pdo();
            $hasLock = Schema::has('email_outbox', 'locked_at');
            $lockCondition = $hasLock
                ? "(status = 'sending' AND (locked_at IS NULL OR locked_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)))"
                : '0 = 1';

            $where = "((status IN ('queued','failed') AND next_attempt_at <= UTC_TIMESTAMP()) OR $lockCondition) AND attempts < 8";
            $params = [];
            if ($messageId !== null && $messageId > 0) {
                $where .= ' AND id = :id';
                $params[':id'] = $messageId;
            }
            $stmt = $pdo->prepare("SELECT * FROM email_outbox WHERE $where ORDER BY id ASC LIMIT $limit");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                if ($hasLock) {
                    $pdo->prepare("UPDATE email_outbox SET status='sending', attempts=attempts+1, locked_at=UTC_TIMESTAMP() WHERE id=:id")
                        ->execute([':id' => $row['id']]);
                } else {
                    $pdo->prepare("UPDATE email_outbox SET status='sending', attempts=attempts+1 WHERE id=:id")
                        ->execute([':id' => $row['id']]);
                }

                $ok = \Matrimony\Mail\Mailer::send($row['recipient_email'], $row['subject'], $row['body_html'], $row['body_text']);
                if ($ok === true) {
                    $lockReset = $hasLock ? ', locked_at=NULL' : '';
                    $pdo->prepare("UPDATE email_outbox SET status='sent', sent_at=UTC_TIMESTAMP(), last_error=NULL$lockReset WHERE id=:id")
                        ->execute([':id' => $row['id']]);
                    $sent++;
                } else {
                    $err = is_string($ok) ? substr($ok, 0, 500) : 'send failed';
                    $lockReset = $hasLock ? ', locked_at=NULL' : '';
                    $pdo->prepare("UPDATE email_outbox SET status='failed', last_error=:e$lockReset, next_attempt_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL POW(2, LEAST(attempts,6)) MINUTE) WHERE id=:id")
                        ->execute([':e' => $err, ':id' => $row['id']]);
                    $failed++;
                }
            }
        } catch (\Throwable $e) {
            @error_log('EmailOutboxService::processDue failed: ' . substr($e->getMessage(), 0, 300));
        }
        return [$sent, $failed];
    }
}
