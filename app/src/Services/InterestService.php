<?php
declare(strict_types=1);

namespace Matrimony\Services;

use Matrimony\Database\Connection;
use Matrimony\Security\AuditService;
use Matrimony\Security\RateLimiter;

/**
 * Server-side interest policy: active paid membership required.
 * Front-end checks are UX only; this is enforcement.
 */
final class InterestService
{
    public static function canSend(int $viewerId, int $targetId): array
    {
        if ($viewerId === $targetId) {
            return ['ok' => false, 'error' => 'Cannot send interest to yourself', 'code' => 'self'];
        }
        $pdo = Connection::pdo();
        $s = $pdo->prepare('SELECT is_active FROM users WHERE id = :id LIMIT 1');
        $s->execute([':id' => $targetId]);
        if (!$s->fetchColumn()) {
            return ['ok' => false, 'error' => 'Target profile is not active', 'code' => 'target_inactive'];
        }
        // Blocked?
        $s = $pdo->prepare('SELECT 1 FROM profile_blocks WHERE (blocker_id=:a1 AND blocked_id=:b1) OR (blocker_id=:b2 AND blocked_id=:a2) LIMIT 1');
        $s->execute([':a1' => $viewerId, ':b1' => $targetId, ':b2' => $viewerId, ':a2' => $targetId]);
        if ($s->fetchColumn()) {
            return ['ok' => false, 'error' => 'This action is not allowed for this profile', 'code' => 'blocked'];
        }
        // Target privacy: receive_interests
        try {
            $s = $pdo->prepare('SELECT receive_interests FROM privacy_settings WHERE user_id = :t LIMIT 1');
            $s->execute([':t' => $targetId]);
            $pr = $s->fetchColumn();
            if ($pr !== false && (int) $pr === 0) {
                return ['ok' => false, 'error' => 'This member is not accepting interests', 'code' => 'privacy'];
            }
        } catch (\Throwable $e) {
        }
        // Membership gate
        $gate = MembershipService::canSendInterest($viewerId);
        if (!$gate['ok']) {
            try {
                $s = $pdo->prepare('SELECT id,status,starts_at,ends_at FROM memberships WHERE user_id = :u ORDER BY id DESC LIMIT 1');
                $s->execute([':u' => $viewerId]);
                if ($row = $s->fetch()) {
                    @error_log('Interest gate: user ' . $viewerId . ' has membership ' . json_encode($row));
                }
            } catch (\Throwable $e) {
            }
            return ['ok' => false, 'error' => 'An active membership is required to send interests. Please choose a plan.', 'code' => 'no_active_plan', 'redirect' => '/plans'];
        }
        // Duplicate active interest guard
        $s = $pdo->prepare("SELECT status FROM matches WHERE user_id = :u AND target_id = :t LIMIT 1");
        $s->execute([':u' => $viewerId, ':t' => $targetId]);
        $st = $s->fetchColumn();
        if (in_array($st, ['interested', 'mutual'], true)) {
            return ['ok' => false, 'error' => 'Interest already sent', 'code' => 'duplicate'];
        }
        // Rate limit: 30 interests/hour per user
        if (!RateLimiter::attempt(RateLimiter::keyFor('interest', (string) $viewerId), 30, 3600)) {
            return ['ok' => false, 'error' => 'Too many requests. Please slow down.', 'code' => 'rate_limited'];
        }
        return ['ok' => true, 'membership' => $gate['membership']];
    }
}
