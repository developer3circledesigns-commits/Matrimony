<?php
declare(strict_types=1);

namespace Matrimony\Services;

use Matrimony\Database\Connection;

/**
 * Queue emails AFTER committing business state; cron retries delivery.
 * SMTP failure must never roll back a verified payment/activation.
 */
final class EmailOutboxService
{
    public static function queue(int $userId, string $templateKey, array $vars = [], ?string $toEmail = null): void
    {
        try {
            $pdo = Connection::pdo();
            if ($toEmail === null && $userId > 0) {
                $s = $pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
                $s->execute([':id' => $userId]);
                $toEmail = (string) $s->fetchColumn();
            }
            if (!$toEmail || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            [$subject, $html, $text] = self::render($templateKey, $vars);
            $stmt = $pdo->prepare('INSERT INTO email_outbox (recipient_email, recipient_user_id, template_key, subject, body_html, body_text) VALUES (:e,:u,:k,:s,:h,:t)');
            $stmt->execute([':e' => $toEmail, ':u' => $userId > 0 ? $userId : null, ':k' => $templateKey, ':s' => $subject, ':h' => $html, ':t' => $text]);
        } catch (\Throwable $e) {
            @error_log('EmailOutboxService::queue failed: ' . $e->getMessage());
        }
    }

    public static function queueToAdmins(string $templateKey, array $vars = []): void
    {
        try {
            $pdo = Connection::pdo();
            $rows = $pdo->query("SELECT id, email FROM users WHERE role IN ('admin','super_admin') AND account_status = 'active'")->fetchAll();
            foreach ($rows as $r) {
                self::queue((int) $r['id'], $templateKey, $vars, $r['email']);
            }
        } catch (\Throwable $e) {
            @error_log('EmailOutboxService::queueToAdmins failed: ' . $e->getMessage());
        }
    }

    /** @return array{string,string,string} */
    public static function render(string $key, array $v): array
    {
        $e = fn($x) => htmlspecialchars((string) ($x ?? ''), ENT_QUOTES, 'UTF-8');
        $plan = $e($v['plan_name_snapshot'] ?? $v['plan'] ?? '');
        $ref = $e($v['public_reference'] ?? $v['reference'] ?? '');
        $amt = isset($v['amount_paise']) ? PlanService::formatINR((int) $v['amount_paise']) : $e($v['amount'] ?? '');
        $app = $e(getenv('APP_NAME') ?: 'Matrimony');
        $site = function_exists('url') ? url() : (string) (getenv('APP_URL') ?: '');
        switch ($key) {
            case 'payment_received_pending':
                $s = "Payment received — awaiting activation [$ref]";
                $b = "<p>Hi,</p><p>We received your payment of <b>$amt</b> for <b>$plan</b> (ref <b>$ref</b>). It is verified and awaiting admin activation. We will email you once activated.</p>";
                break;
            case 'payment_needs_review':
                $s = "Payment needs review [$ref]";
                $b = "<p>Hi,</p><p>Your payment (ref <b>$ref</b>) needs a quick manual review. Our team will contact you shortly.</p>";
                break;
            case 'admin_activation_needed':
                $s = "Action needed: verified payment awaiting activation [$ref]";
                $b = "<p>A payment of <b>$amt</b> for <b>$plan</b> (ref <b>$ref</b>) is verified and awaiting activation. Please review the activation queue.</p>";
                break;
            case 'membership_activated':
                $s = "Your $plan membership is active";
                $b = "<p>Hi,</p><p>Your <b>$plan</b> membership is now active. Amount paid: <b>$amt</b>.</p>";
                break;
            case 'membership_suspended':
                $s = "Membership update";
                $b = "<p>Hi,</p><p>Your membership status changed. Reason: " . $e($v['reason'] ?? '') . ". Contact support for help.</p>";
                break;
            case 'contact_inquiry':
                $s = (string) ($v['subject_line'] ?? ("[$app] Contact form message"));
                $b = (string) ($v['body_html'] ?? ('<p>' . $e($v['message'] ?? '') . '</p>'));
                break;
            default:
                $s = "$app notification";
                $b = '<p>' . $e($v['message'] ?? 'You have a new notification.') . '</p>';
                break;
        }
        if ($site !== '') {
            $b .= '<hr><p style="color:#888888;font-size:12px">' . $app . ' &mdash; <a href="' . $e($site) . '">' . $e($site) . '</a></p>';
        }
        return [$s, $b, strip_tags($b)];
    }

    /** Send due messages (used by cron + admin retry). Returns [sent, failed]. */
    public static function processDue(int $limit = 25): array
    {
        $sent = 0;
        $failed = 0;
        try {
            $pdo = Connection::pdo();
            $stmt = $pdo->prepare("SELECT * FROM email_outbox WHERE status IN ('queued','failed') AND next_attempt_at <= NOW() AND attempts < 8 ORDER BY id ASC LIMIT $limit");
            $stmt->execute();
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $pdo->prepare("UPDATE email_outbox SET status='sending', attempts=attempts+1 WHERE id=:id")->execute([':id' => $row['id']]);
                $ok = \Matrimony\Mail\Mailer::send($row['recipient_email'], $row['subject'], $row['body_html'], $row['body_text']);
                if ($ok === true) {
                    $pdo->prepare("UPDATE email_outbox SET status='sent', sent_at=NOW(), last_error=NULL WHERE id=:id")->execute([':id' => $row['id']]);
                    $sent++;
                } else {
                    $err = is_string($ok) ? substr($ok, 0, 500) : 'send failed';
                    $pdo->prepare("UPDATE email_outbox SET status='failed', last_error=:e, next_attempt_at=DATE_ADD(NOW(), INTERVAL POW(2, LEAST(attempts,6)) MINUTE) WHERE id=:id")->execute([':e' => $err, ':id' => $row['id']]);
                    $failed++;
                }
            }
        } catch (\Throwable $e) {
            @error_log('EmailOutboxService::processDue failed: ' . substr($e->getMessage(), 0, 300));
        }
        return [$sent, $failed];
    }
}
