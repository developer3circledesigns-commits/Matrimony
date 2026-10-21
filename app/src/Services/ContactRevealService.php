<?php
declare(strict_types=1);

namespace Matrimony\Services;

use Matrimony\Database\Connection;
use Matrimony\Security\AuditService;

/**
 * Transactional, atomic contact-reveal enforcement.
 * A "contact" = unique member whose protected contact info is revealed.
 */
final class ContactRevealService
{
    /** Reveal contact; returns contact array or error. Atomic + idempotent. */
    public static function reveal(int $viewerId, int $targetId): array
    {
        $check = MembershipService::canReveal($viewerId, $targetId);
        if (!$check['ok']) {
            return ['success' => false, 'error' => self::message($check['reason'] ?? ''), 'code' => $check['reason'] ?? 'denied'];
        }
        $pdo = Connection::pdo();
        try {
            $pdo->beginTransaction();
            // Lock viewer's active membership row
            $m = $check['membership'];
            $stmt = $pdo->prepare('SELECT * FROM memberships WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $m['id']]);
            $locked = $stmt->fetch();
            if (!$locked || $locked['status'] !== 'active' || strtotime($locked['ends_at']) <= time()) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'Membership is no longer active', 'code' => 'no_active_plan'];
            }
            // Already revealed? return contact without consuming
            $stmt = $pdo->prepare('SELECT id FROM contact_reveals WHERE viewer_user_id = :v AND target_user_id = :t LIMIT 1');
            $stmt->execute([':v' => $viewerId, ':t' => $targetId]);
            if ($stmt->fetchColumn()) {
                $pdo->commit();
                AuditService::log($viewerId, 'contact.reveal_repeat', 'user', $targetId, []);
                return ['success' => true, 'repeat' => true, 'contact' => self::contactOf($targetId), 'remaining' => MembershipService::enrich($locked)['contacts_remaining']];
            }
            // Allowance check inside lock — legacy rows without snapshots are denied (not unlimited).
            $legacyNoCap = !array_key_exists('contact_limit_snapshot', $locked) && !array_key_exists('is_unlimited_snapshot', $locked);
            $unlimited = !$legacyNoCap && (int) ($locked['is_unlimited_snapshot'] ?? 0) === 1;
            if ($legacyNoCap) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'Membership data needs an upgrade. Please contact support.', 'code' => 'legacy_membership'];
            }
            if (!$unlimited) {
                $used = MembershipService::profileUsageCount((int) $locked['id'], (int) $locked['user_id'], (string) $locked['starts_at'], (string) $locked['ends_at']);
                $limit = ($locked['contact_limit_snapshot'] ?? null) !== null ? (int) $locked['contact_limit_snapshot'] : 0;
                if ($used >= $limit && !MembershipService::profileCountedInMembership($locked, $targetId)) {
                    $pdo->rollBack();
                    return ['success' => false, 'error' => 'Contact allowance exhausted. Please upgrade your plan.', 'code' => 'allowance_exhausted'];
                }
            }
            $stmt = $pdo->prepare('INSERT INTO contact_reveals (viewer_user_id, target_user_id, membership_id) VALUES (:v,:t,:m)');
            $stmt->execute([':v' => $viewerId, ':t' => $targetId, ':m' => $locked['id']]);
            $newUsed = MembershipService::profileUsageCount((int) $locked['id'], (int) $locked['user_id'], (string) $locked['starts_at'], (string) $locked['ends_at']);
            if ($newUsed >= 0) {
                $pdo->prepare('UPDATE memberships SET contacts_used = :used WHERE id = :mid')->execute([':used' => $newUsed, ':mid' => $locked['id']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Unique race → treat as repeat
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return ['success' => true, 'repeat' => true, 'contact' => self::contactOf($targetId)];
            }
            return ['success' => false, 'error' => 'Could not reveal contact. Try again.'];
        }
        AuditService::log($viewerId, 'contact.reveal', 'user', $targetId, ['membership_id' => $m['id']]);
        $fresh = MembershipService::activeFor($viewerId);
        return ['success' => true, 'repeat' => false, 'contact' => self::contactOf($targetId), 'remaining' => $fresh['contacts_remaining'] ?? null];
    }

    public static function hasRevealed(int $viewerId, int $targetId): bool
    {
        try {
            $pdo = Connection::pdo();
            $s = $pdo->prepare('SELECT 1 FROM contact_reveals WHERE viewer_user_id = :v AND target_user_id = :t LIMIT 1');
            $s->execute([':v' => $viewerId, ':t' => $targetId]);
            return (bool) $s->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Protected contact payload — only returned AFTER authorization succeeds. */
    public static function contactOf(int $targetId): array
    {
        $pdo = Connection::pdo();
        $phoneSelect = \Matrimony\Database\Schema::has('users', 'phone') ? 'u.phone' : 'NULL AS phone';
        $stmt = $pdo->prepare("SELECT u.email, $phoneSelect, p.first_name, p.last_name, p.city, p.state FROM users u JOIN profiles p ON p.user_id = u.id WHERE u.id = :id LIMIT 1");
        $stmt->execute([':id' => $targetId]);
        $r = $stmt->fetch() ?: [];
        return [
            'name' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
            'email' => $r['email'] ?? null,
            'phone' => $r['phone'] ?? null,
            'city' => $r['city'] ?? null,
            'state' => $r['state'] ?? null,
        ];
    }

    /** Masked placeholder for unauthorized viewers. */
    public static function masked(): array
    {
        return ['locked' => true, 'email' => '••••••', 'phone' => '••••••••••'];
    }

    private static function message(?string $code): string
    {
        return match ($code) {
            'no_active_plan' => 'An active membership is required. Please choose a plan.',
            'allowance_exhausted' => 'Contact allowance exhausted. Please upgrade your plan.',
            'blocked' => 'This action is not allowed for this profile.',
            'privacy_denied' => 'This member has restricted contact visibility.',
            'target_unavailable' => 'This profile is not available.',
            default => 'Contact reveal is not allowed.',
        };
    }
}
