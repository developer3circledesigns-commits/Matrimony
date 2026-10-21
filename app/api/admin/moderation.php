<?php
declare(strict_types=1);
// POST /api/admin/profiles/moderate | /api/admin/reports/review
//      /api/admin/outbox/retry  | /api/admin/memberships/suspend
require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Authorization;
use Matrimony\Http\Csrf;
use Matrimony\Database\Connection;
use Matrimony\Security\AuditService;
use Matrimony\Services\MembershipService;
use Matrimony\Services\EmailOutboxService;

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
// High-risk mutations additionally require recent re-authentication —
// checked only after authentication so unauthenticated callers can't probe it.
if (str_ends_with($path, '/moderate') || str_ends_with($path, '/review')) {
    Authorization::requireReauth();
}

$rlKey = 'admin-moderation:' . (int) $admin['id'];
if (!\Matrimony\Security\RateLimiter::attempt($rlKey, 60, 3600)) {
    admin_respond(['success' => false, 'error' => 'Too many requests', 'message' => 'Please slow down.'], admin_return_to('/admin/dashboard'), 429);
}
if (str_ends_with($path, '/moderate')) {
    $fallback = admin_return_to('/admin/profiles');
    $uid = (int) ($_POST['user_id'] ?? 0);
    $st = (string) ($_POST['status'] ?? '');
    $reason = substr(trim((string) ($_POST['reason'] ?? '')), 0, 255);
    if (!in_array($st, ['approved', 'pending', 'rejected', 'hidden', 'flagged'], true) || $uid <= 0) {
        admin_respond([
            'success' => false,
            'error' => 'Invalid input',
            'message' => 'Not applied: choose a valid moderation status.',
        ], $fallback, 400);
    }
    $pdo = Connection::pdo();
    $exists = $pdo->prepare('SELECT 1 FROM profiles WHERE user_id = :u LIMIT 1');
    $exists->execute([':u' => $uid]);
    if (!$exists->fetchColumn()) {
        admin_respond(['success' => false, 'error' => 'User not found', 'message' => 'Profile not found for that user.'], $fallback, 404);
    }
    $stmt = $pdo->prepare('UPDATE profiles SET moderation_status = :s, moderation_reason = :r, is_hidden = :h WHERE user_id = :u');
    $stmt->execute([':s' => $st, ':r' => $reason, ':h' => in_array($st, ['hidden', 'rejected'], true) ? 1 : 0, ':u' => $uid]);
    if ($stmt->rowCount() === 0) {
        admin_respond(['success' => false, 'error' => 'Not applied', 'message' => 'No matching profile row.'], $fallback, 404);
    }
    AuditService::log((int) $admin['id'], 'admin.profile.moderate', 'user', $uid, ['status' => $st, 'reason' => $reason]);
    admin_respond([
        'success' => true,
        'message' => 'Profile #' . $uid . ' is now ' . str_replace('_', ' ', $st) . '.',
    ], $fallback);
}

if (str_ends_with($path, '/review')) {
    $fallback = admin_return_to('/admin/reports');
    $id = (int) ($_POST['id'] ?? 0);
    $st = (string) ($_POST['status'] ?? '');
    $note = substr(trim((string) ($_POST['note'] ?? '')), 0, 500);
    if (!in_array($st, ['open', 'dismissed', 'warned', 'hidden', 'suspended', 'escalated'], true) || $id <= 0) {
        admin_respond([
            'success' => false,
            'error' => 'Invalid input',
            'message' => 'Not applied: choose a valid report status.',
        ], $fallback, 400);
    }
    $pdo = Connection::pdo();
    $exists = $pdo->prepare('SELECT 1 FROM profile_reports WHERE id = :id LIMIT 1');
    $exists->execute([':id' => $id]);
    if (!$exists->fetchColumn()) {
        admin_respond(['success' => false, 'error' => 'Not found', 'message' => 'Report not found.'], $fallback, 404);
    }
    $pdo->prepare('UPDATE profile_reports SET status = :s, reviewed_by = :a, reviewed_at = NOW(), review_note = :n WHERE id = :id')
        ->execute([':s' => $st, ':a' => $admin['id'], ':n' => $note, ':id' => $id]);
    AuditService::log((int) $admin['id'], 'admin.report.review', 'report', $id, ['status' => $st, 'note' => $note]);
    admin_respond([
        'success' => true,
        'message' => 'Report #' . $id . ' marked ' . $st . '.',
    ], $fallback);
}

if (str_ends_with($path, '/retry')) {
    $fallback = admin_return_to('/admin/outbox');
    Authorization::requireReauth();
    $messageId = (int) ($_POST['message_id'] ?? 0);
    if ($messageId > 0) {
        $s = Connection::pdo()->prepare("SELECT status FROM email_outbox WHERE id = :id LIMIT 1");
        $s->execute([':id' => $messageId]);
        $status = (string) ($s->fetchColumn() ?: '');
        if ($status !== 'failed') {
            admin_respond([
                'success' => false,
                'error' => 'Only failed messages can be retried',
                'message' => 'That message is not in the failed queue.',
            ], $fallback, 422);
        }
        // Reset the backoff so an explicit admin retry runs immediately.
        Connection::pdo()->prepare("UPDATE email_outbox SET next_attempt_at = UTC_TIMESTAMP(), last_error = NULL WHERE id = :id AND status = 'failed'")
            ->execute([':id' => $messageId]);
        [$sent, $failed] = EmailOutboxService::processDue(1, $messageId);
    } else {
        [$sent, $failed] = EmailOutboxService::processDue(25);
    }
    AuditService::log((int) $admin['id'], 'admin.outbox.retry', null, null, ['sent' => $sent, 'failed' => $failed, 'message_id' => $messageId ?: null]);
    admin_respond([
        'success' => $failed === 0,
        'sent' => $sent,
        'failed' => $failed,
        'message' => $messageId > 0
            ? 'Retry finished: ' . ($sent === 1 ? 'delivered' : 'not delivered') . '.'
            : ($sent . ' email' . ($sent === 1 ? '' : 's') . ' sent, ' . $failed . ' failed.'),
    ], $fallback, $failed === 0 ? 200 : 422);
}

if (str_ends_with($path, '/suspend')) {
    $fallback = admin_return_to('/admin/payments');
    Authorization::requireReauth();
    $res = MembershipService::suspend(
        (int) $admin['id'],
        (int) ($_POST['membership_id'] ?? 0),
        (string) ($_POST['reason'] ?? '')
    );
    $ok = !empty($res['success']);
    $res['message'] = $ok ? 'Membership suspended.' : 'Not suspended: ' . (string) ($res['error'] ?? 'unknown error');
    admin_respond($res, $fallback, $ok ? 200 : 422);
}

admin_respond(['success' => false, 'error' => 'Not found', 'message' => 'Unknown moderation action.'], '/admin/dashboard', 404);
