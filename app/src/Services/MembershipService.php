<?php
declare(strict_types=1);

namespace Matrimony\Services;

use Matrimony\Database\Connection;
use Matrimony\Database\Schema;
use Matrimony\Security\AuditService;

/**
 * Membership lifecycle. Expiry is ALWAYS checked against server time
 * (starts_at/ends_at/status) — never relies on cron alone.
 */
final class MembershipService
{
    /** @return array|null active membership row with computed remaining */
    public static function activeFor(int $userId): ?array
    {
        $pdo = Connection::pdo();
        $queries = [];
        try {
            if (Schema::has('membership_plans', 'slug')) {
                $queries[] = "SELECT m.*, p.slug AS plan_slug, p.name AS plan_name
                    FROM memberships m LEFT JOIN membership_plans p ON p.id = m.plan_id
                    WHERE m.user_id = :u AND m.status = 'active' AND m.starts_at <= UTC_TIMESTAMP() AND m.ends_at > UTC_TIMESTAMP()
                    ORDER BY m.ends_at DESC LIMIT 1";
            }
        } catch (\Throwable $e) {
        }
        $queries[] = "SELECT m.*, NULL AS plan_slug, NULL AS plan_name
            FROM memberships m
            WHERE m.user_id = :u AND m.status = 'active' AND m.starts_at <= UTC_TIMESTAMP() AND m.ends_at > UTC_TIMESTAMP()
            ORDER BY m.ends_at DESC LIMIT 1";

        foreach ($queries as $sql) {
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':u' => $userId]);
                $m = $stmt->fetch();
                if ($m) {
                    return self::enrich($m);
                }
            } catch (\Throwable $e) {
                @error_log('MembershipService::activeFor query failed for user ' . $userId . ': ' . $e->getMessage());
            }
        }

        // Tolerate small timezone/clock drift between PHP and MySQL around starts_at.
        try {
            $stmt = $pdo->prepare("SELECT m.*, NULL AS plan_slug, NULL AS plan_name
                FROM memberships m
                WHERE m.user_id = :u AND m.status = 'active'
                  AND (m.starts_at IS NULL OR m.starts_at <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 6 HOUR))
                  AND m.ends_at > UTC_TIMESTAMP()
                ORDER BY m.ends_at DESC LIMIT 1");
            $stmt->execute([':u' => $userId]);
            $m = $stmt->fetch();
            if ($m) {
                @error_log('MembershipService::activeFor used start-time tolerance for user ' . $userId . ' membership ' . $m['id']);
                return self::enrich($m);
            }
        } catch (\Throwable $e) {
            @error_log('MembershipService::activeFor tolerance query failed for user ' . $userId . ': ' . $e->getMessage());
        }

        return null;
    }

    public static function enrich(array $m): array
    {
        $pdo = Connection::pdo();
        // Source of truth for usage = reveal ledger count
        try {
            $s = $pdo->prepare('SELECT COUNT(*) FROM contact_reveals WHERE membership_id = :mid');
            $s->execute([':mid' => $m['id']]);
            $used = (int) $s->fetchColumn();
        } catch (\Throwable $e) {
            $used = (int) ($m['contacts_used'] ?? 0);
        }
        $m['contacts_used'] = $used;
        try {
            $s = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE user_id = :u AND status IN ('interested','mutual') AND created_at >= :starts AND created_at <= :ends");
            $s->execute([':u' => $m['user_id'], ':starts' => $m['starts_at'], ':ends' => $m['ends_at']]);
            $m['interests_sent'] = (int) $s->fetchColumn();
        } catch (\Throwable $e) {
            $m['interests_sent'] = 0;
        }
        // Pre-migration rows without snapshots must NOT be treated as unlimited.
        $m['legacy_no_cap'] = !array_key_exists('contact_limit_snapshot', $m) && !array_key_exists('is_unlimited_snapshot', $m);
        $unlimited = !$m['legacy_no_cap'] && (int) ($m['is_unlimited_snapshot'] ?? 0) === 1;
        $limit = array_key_exists('contact_limit_snapshot', $m) && $m['contact_limit_snapshot'] !== null ? (int) $m['contact_limit_snapshot'] : null;
        if ($m['legacy_no_cap']) {
            $m['contacts_remaining'] = 0;
        } else {
            $m['contacts_remaining'] = $unlimited ? null : max(0, ($limit ?? 0) - $used);
        }
        $endsAt = strtotime((string) $m['ends_at'] . ' UTC');
        $m['is_expired'] = $endsAt !== false && $endsAt <= time();
        $m['days_remaining'] = $endsAt === false ? 0 : max(0, (int) floor(($endsAt - time()) / 86400));
        return $m;
    }

    public static function canSendInterest(int $userId): array
    {
        $m = self::activeFor($userId);
        if (!$m) {
            return ['ok' => false, 'reason' => 'no_active_plan', 'membership' => null];
        }
        return ['ok' => true, 'membership' => $m];
    }

    public static function canReveal(int $viewerId, int $targetId): array
    {
        if ($viewerId === $targetId) {
            return ['ok' => false, 'reason' => 'self'];
        }
        $pdo = Connection::pdo();
        // Blocked either direction?
        $s = $pdo->prepare('SELECT 1 FROM profile_blocks WHERE (blocker_id=:a1 AND blocked_id=:b1) OR (blocker_id=:b2 AND blocked_id=:a2) LIMIT 1');
        $s->execute([':a1' => $viewerId, ':b1' => $targetId, ':b2' => $viewerId, ':a2' => $targetId]);
        if ($s->fetchColumn()) {
            return ['ok' => false, 'reason' => 'blocked'];
        }
        // Target must be visible/approved
        $s = $pdo->prepare('SELECT moderation_status, is_hidden FROM profiles WHERE user_id = :t LIMIT 1');
        $s->execute([':t' => $targetId]);
        $tp = $s->fetch();
        if (!$tp || ($tp['moderation_status'] ?? 'approved') !== 'approved' || (int) ($tp['is_hidden'] ?? 0) === 1) {
            return ['ok' => false, 'reason' => 'target_unavailable'];
        }
        // Target privacy: must allow reveal
        try {
            $s = $pdo->prepare('SELECT allow_contact_reveal, profile_visibility FROM privacy_settings WHERE user_id = :t LIMIT 1');
            $s->execute([':t' => $targetId]);
            $priv = $s->fetch();
            if ($priv && (int) ($priv['allow_contact_reveal'] ?? 1) === 0) {
                return ['ok' => false, 'reason' => 'privacy_denied'];
            }
        } catch (\Throwable $e) {
        }
        $m = self::activeFor($viewerId);
        if (!$m) {
            return ['ok' => false, 'reason' => 'no_active_plan'];
        }
        // Already revealed? free repeat
        $s = $pdo->prepare('SELECT id FROM contact_reveals WHERE viewer_user_id = :v AND target_user_id = :t LIMIT 1');
        $s->execute([':v' => $viewerId, ':t' => $targetId]);
        if ($s->fetchColumn()) {
            return ['ok' => true, 'already' => true, 'membership' => $m];
        }
        $unlimited = empty($m['legacy_no_cap']) && (int) ($m['is_unlimited_snapshot'] ?? 0) === 1;
        if (!$unlimited && ($m['contacts_remaining'] ?? 0) <= 0) {
            return ['ok' => false, 'reason' => 'allowance_exhausted', 'membership' => $m];
        }
        return ['ok' => true, 'already' => false, 'membership' => $m];
    }

    /**
     * Required admin activation transaction (§12 of plan).
     * Returns ['success'=>bool, ...]. Rolls back on any check failure.
     */
    public static function activate(int $adminId, int $activationId, string $mode = 'replace', string $reason = ''): array
    {
        $pdo = Connection::pdo();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT * FROM activation_requests WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $activationId]);
            $ar = $stmt->fetch();
            if (!$ar) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'Activation request not found'];
            }
            if ($ar['status'] !== 'awaiting_admin' && $ar['status'] !== 'on_hold') {
                // Idempotent: already-approved requests succeed without creating another membership
                if ($ar['status'] === 'approved_activated' && !empty($ar['activated_membership_id'])) {
                    $pdo->commit();
                    return ['success' => true, 'duplicate' => true, 'membership_id' => (int) $ar['activated_membership_id']];
                }
                $pdo->rollBack();
                return ['success' => false, 'error' => 'Request is not awaiting activation (status: ' . $ar['status'] . ')'];
            }
            $stmt = $pdo->prepare('SELECT * FROM purchases WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $ar['purchase_id']]);
            $p = $stmt->fetch();
            if (!$p) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'Purchase not found'];
            }
            if ($p['payment_status'] !== 'paid_verified') {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'Payment is not verified (status: ' . $p['payment_status'] . '). Activation is forbidden.'];
            }
            // Duplicate activation guard
            $stmt = $pdo->prepare('SELECT id FROM memberships WHERE purchase_id = :pid LIMIT 1');
            $stmt->execute([':pid' => $p['id']]);
            if ($stmt->fetchColumn()) {
                // Link + close request idempotently
                $pdo->prepare('UPDATE activation_requests SET status = :s, reviewed_by = :a, reviewed_at = UTC_TIMESTAMP(), decision_reason = :r WHERE id = :id')
                    ->execute([':s' => 'approved_activated', ':a' => $adminId, ':r' => 'Duplicate activation suppressed (idempotent)', ':id' => $activationId]);
                $pdo->commit();
                return ['success' => true, 'duplicate' => true];
            }
            $userId = (int) $p['user_id'];
            $existing = self::activeFor($userId);
            if ($existing && $mode === 'reject_if_active') {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'User already has an active plan. Choose replace or queue.'];
            }
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $start = $now;
            if ($existing && $mode === 'extend') {
                $endPrev = new \DateTimeImmutable($existing['ends_at'], new \DateTimeZone('UTC'));
                if ($endPrev > $now) {
                    $start = $endPrev;
                }
            }
            if ($existing && $mode === 'replace') {
                $pdo->prepare("UPDATE memberships SET status = 'replaced' WHERE id = :id")->execute([':id' => $existing['id']]);
            }
            $days = max(1, (int) $p['duration_days_snapshot']);
            $ends = $start->modify('+' . $days . ' days');
            $stmt = $pdo->prepare("INSERT INTO memberships (user_id, plan_id, purchase_id, plan_name_snapshot, amount_paise, currency, duration_days_snapshot, contact_limit_snapshot, is_unlimited_snapshot, status, starts_at, ends_at, activated_by, activated_at)
                VALUES (:u,:pl,:pur,:nm,:amt,:cur,:dur,:lim,:unl,'active',:st,:en,:ab,UTC_TIMESTAMP())");
            $stmt->execute([
                ':u' => $userId, ':pl' => $p['plan_id'], ':pur' => $p['id'],
                ':nm' => $p['plan_name_snapshot'], ':amt' => $p['amount_paise'], ':cur' => $p['currency'],
                ':dur' => $p['duration_days_snapshot'], ':lim' => $p['contact_limit_snapshot'], ':unl' => $p['is_unlimited_snapshot'],
                ':st' => $start->format('Y-m-d H:i:s'), ':en' => $ends->format('Y-m-d H:i:s'), ':ab' => $adminId,
            ]);
            $mid = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE activation_requests SET status = :s, reviewed_by = :a, reviewed_at = UTC_TIMESTAMP(), decision_reason = :r, activated_membership_id = :mid WHERE id = :id')
                ->execute([':s' => 'approved_activated', ':a' => $adminId, ':r' => substr($reason ?: ('Activated (' . $mode . ')'), 0, 500), ':mid' => $mid, ':id' => $activationId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            @error_log('MembershipService::activate failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Could not activate the membership. Please try again.'];
        }
        AuditService::log($adminId, 'membership.activate', 'activation_request', $activationId, ['purchase_id' => $p['id'], 'membership_id' => $mid, 'mode' => $mode]);
        NotificationService::notify((int) $p['user_id'], 'membership_activated', 'Plan activated', 'Your ' . $p['plan_name_snapshot'] . ' is now active.', 'membership', $mid);
        PaymentNotificationService::event('membership.activated', $p, ['dedupe' => 'membership:' . $mid]);
        return ['success' => true, 'membership_id' => $mid];
    }

    public static function suspend(int $adminId, int $membershipId, string $reason): array
    {
        if (trim($reason) === '') {
            return ['success' => false, 'error' => 'Reason is required'];
        }
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('SELECT * FROM memberships WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $membershipId]);
        $m = $stmt->fetch();
        if (!$m) {
            return ['success' => false, 'error' => 'Membership not found'];
        }
        if ($m['status'] !== 'active') {
            return ['success' => false, 'error' => 'Only active memberships can be suspended'];
        }
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE memberships SET status = 'suspended', suspension_reason = :r WHERE id = :id AND status='active'")
                ->execute([':r' => substr($reason, 0, 255), ':id' => $membershipId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }
        NotificationService::notify((int) $m['user_id'], 'membership_suspended', 'Membership suspended', 'Your membership was suspended. Reason: ' . $reason, 'membership', $membershipId);
        PaymentNotificationService::event('membership.suspended', $m, [
            'reason' => $reason,
            'dedupe' => 'membership-suspend:' . $membershipId . ':' . substr(hash('sha256', $reason), 0, 12),
        ]);
        AuditService::log($adminId, 'membership.suspend', 'membership', $membershipId, ['reason' => $reason]);
        return ['success' => true];
    }

    /**
     * Suspend any active membership linked to a purchase (refund/dispute).
     * Used by webhooks where there is no admin actor. Returns affected rows.
     */
    public static function suspendForPurchase(int $purchaseId, string $reason): int
    {
        try {
            $pdo = Connection::pdo();
            $stmt = $pdo->prepare("SELECT * FROM memberships WHERE purchase_id = :pid AND status = 'active'");
            $stmt->execute([':pid' => $purchaseId]);
            $rows = $stmt->fetchAll();
            if (!$rows) {
                return 0;
            }
            $reason = substr(trim($reason) !== '' ? trim($reason) : 'Payment reversed — pending review', 0, 255);
            $pdo->prepare("UPDATE memberships SET status = 'suspended', suspension_reason = :r WHERE purchase_id = :pid AND status = 'active'")
                ->execute([':r' => $reason, ':pid' => $purchaseId]);
            foreach ($rows as $m) {
                NotificationService::notify((int) $m['user_id'], 'membership_suspended', 'Membership suspended', 'Your membership was suspended. Reason: ' . $reason, 'membership', (int) $m['id']);
                AuditService::log(null, 'membership.suspend.auto', 'membership', (int) $m['id'], ['purchase_id' => $purchaseId, 'reason' => $reason]);
            }
            return count($rows);
        } catch (\Throwable $e) {
            @error_log('MembershipService::suspendForPurchase failed (' . get_class($e) . ')');
            return 0;
        }
    }

    /** Housekeeping for reporting only — entitlement never depends on this. */
    public static function markExpired(): int
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("UPDATE memberships SET status = 'expired' WHERE status = 'active' AND ends_at <= UTC_TIMESTAMP()");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
