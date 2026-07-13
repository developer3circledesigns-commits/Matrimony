<?php
declare(strict_types=1);

use Matrimony\Database\Connection;
use Matrimony\Http\Auth;

final class ActionController
{
    private const ALLOWED_STATUSES = ['interested', 'shortlisted', 'declined', 'skip'];

    public function perform(int $viewerId, int $targetId, string $status): array
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            return ['success' => false, 'error' => 'Invalid action status'];
        }

        if ($viewerId === $targetId) {
            return ['success' => false, 'error' => 'Cannot act on yourself'];
        }

        $pdo = Connection::pdo();

        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = :id AND is_active = 1");
        $stmt->execute([':id' => $targetId]);
        if (!$stmt->fetch()) {
            return ['success' => false, 'error' => 'Target profile is not active'];
        }

        $stmt = $pdo->prepare("SELECT id, status FROM matches WHERE user_id = :uid AND target_id = :tid");
        $stmt->execute([':uid' => $viewerId, ':tid' => $targetId]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($status === 'skip' && $existing['status'] === 'declined') {
                return ['success' => true, 'status' => 'already_skipped'];
            }
            $stmt = $pdo->prepare("UPDATE matches SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $status, ':id' => $existing['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO matches (user_id, target_id, status, created_at) VALUES (:uid, :tid, :status, NOW())");
            $stmt->execute([':uid' => $viewerId, ':tid' => $targetId, ':status' => $status]);
        }

        $mutual = false;
        if ($status === 'interested') {
            $stmt = $pdo->prepare("SELECT 1 FROM matches WHERE user_id = :target AND target_id = :viewer AND status IN ('interested','mutual')");
            $stmt->execute([':target' => $targetId, ':viewer' => $viewerId]);
            if ($stmt->fetch()) {
                $pdo->prepare("UPDATE matches SET status = 'mutual' WHERE (user_id = :v AND target_id = :t) OR (user_id = :t2 AND target_id = :v2)")
                    ->execute([':v' => $viewerId, ':t' => $targetId, ':t2' => $targetId, ':v2' => $viewerId]);
                $mutual = true;
            }
        }

        if (in_array($status, ['interested', 'shortlisted'], true)) {
            $this->createNotification($targetId, $status, ['user_id' => $viewerId]);
        }

        return ['success' => true, 'status' => $status, 'mutual' => $mutual];
    }

    public function block(int $viewerId, int $targetId): array
    {
        if ($viewerId === $targetId) {
            return ['success' => false, 'error' => 'Cannot block yourself'];
        }

        $pdo = Connection::pdo();

        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = :id AND is_active = 1");
        $stmt->execute([':id' => $targetId]);
        if (!$stmt->fetch()) {
            return ['success' => false, 'error' => 'Target profile is not active'];
        }
        $pdo->prepare("INSERT IGNORE INTO profile_blocks (blocker_id, blocked_id) VALUES (:b, :t)")
            ->execute([':b' => $viewerId, ':t' => $targetId]);
        $pdo->prepare("INSERT INTO matches (user_id, target_id, status, created_at) VALUES (:uid, :tid, 'blocked', NOW()) ON DUPLICATE KEY UPDATE status = 'blocked'")
            ->execute([':uid' => $viewerId, ':tid' => $targetId]);

        return ['success' => true];
    }

    public function report(int $viewerId, int $targetId, string $reason): array
    {
        if (empty($reason)) {
            return ['success' => false, 'error' => 'Reason is required'];
        }

        $pdo = Connection::pdo();
        $pdo->prepare("INSERT INTO profile_reports (reporter_id, profile_id, reason) VALUES (:r, :p, :reason)")
            ->execute([':r' => $viewerId, ':p' => $targetId, ':reason' => $reason]);

        return ['success' => true];
    }

    public function logView(int $viewerId, int $profileId): void
    {
        $pdo = Connection::pdo();
        $pdo->prepare("INSERT INTO profile_views (viewer_id, profile_id) VALUES (:v, :p)")
            ->execute([':v' => $viewerId, ':p' => $profileId]);
    }

    public function getWhoViewedMe(int $userId, int $limit = 20): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("
            SELECT pv.viewer_id, pv.viewed_at,
                   pf.first_name, pf.city, pf.state,
                   (SELECT path FROM profile_photos WHERE user_id = pf.user_id AND is_primary = 1 LIMIT 1) AS primary_photo
            FROM profile_views pv
            JOIN profiles pf ON pf.user_id = pv.viewer_id
            JOIN users u ON u.id = pv.viewer_id AND u.is_active = 1
            WHERE pv.profile_id = :uid
            ORDER BY pv.viewed_at DESC
            LIMIT :lim");
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getWhoShortlistedMe(int $userId, int $limit = 20): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("
            SELECT m.user_id AS viewer_id, m.created_at,
                   pf.first_name, pf.city, pf.state,
                   (SELECT path FROM profile_photos WHERE user_id = pf.user_id AND is_primary = 1 LIMIT 1) AS primary_photo
            FROM matches m
            JOIN profiles pf ON pf.user_id = m.user_id
            JOIN users u ON u.id = m.user_id AND u.is_active = 1
            WHERE m.target_id = :uid AND m.status = 'shortlisted'
            ORDER BY m.created_at DESC
            LIMIT :lim");
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getMutualMatches(int $userId, int $limit = 20): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("
            SELECT m.user_id AS match_id, m.created_at,
                   pf.first_name, pf.city, pf.state,
                   (SELECT path FROM profile_photos WHERE user_id = pf.user_id AND is_primary = 1 LIMIT 1) AS primary_photo
            FROM matches m
            JOIN profiles pf ON pf.user_id = m.user_id
            JOIN users u ON u.id = m.user_id AND u.is_active = 1
            WHERE m.target_id = :uid AND m.status = 'mutual'
            ORDER BY m.created_at DESC
            LIMIT :lim");
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getInterestsReceived(int $userId, int $limit = 5): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("
            SELECT m.user_id, m.created_at,
                   pf.first_name, pf.city, pf.state, pf.gender, pf.date_of_birth,
                   (SELECT path FROM profile_photos WHERE user_id = pf.user_id AND is_primary = 1 LIMIT 1) AS primary_photo
            FROM matches m
            JOIN profiles pf ON pf.user_id = m.user_id
            JOIN users u ON u.id = m.user_id AND u.is_active = 1
            WHERE m.target_id = :uid AND m.status IN ('interested', 'mutual')
            ORDER BY m.created_at DESC
            LIMIT :lim");
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getMyShortlists(int $userId, int $limit = 5): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("
            SELECT m.target_id, m.created_at,
                   pf.first_name, pf.city, pf.state, pf.gender, pf.date_of_birth,
                   (SELECT path FROM profile_photos WHERE user_id = pf.user_id AND is_primary = 1 LIMIT 1) AS primary_photo
            FROM matches m
            JOIN profiles pf ON pf.user_id = m.target_id
            JOIN users u ON u.id = m.target_id AND u.is_active = 1
            WHERE m.user_id = :uid AND m.status = 'shortlisted'
            ORDER BY m.created_at DESC
            LIMIT :lim");
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getNotifications(int $userId): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("
            SELECT * FROM match_notifications
            WHERE user_id = :uid AND read_at IS NULL
            ORDER BY created_at DESC
            LIMIT 50");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function acknowledgeNotifications(int $userId): void
    {
        $pdo = Connection::pdo();
        $pdo->prepare("UPDATE match_notifications SET read_at = NOW() WHERE user_id = :uid AND read_at IS NULL")
            ->execute([':uid' => $userId]);
    }

    public function getOnlineProfiles(int $viewerId, int $minutes = 5, int $limit = 20): array
    {
        $pdo = Connection::pdo();
        $viewer = $this->getProfileById($viewerId);
        if (!$viewer) return [];

        $opposite = $viewer['gender'] === 'male' ? 'female' : 'male';
        $stmt = $pdo->prepare("
            SELECT p.user_id, p.first_name, p.city, p.state,
                   u.last_login_at,
                   (SELECT path FROM profile_photos WHERE user_id = p.user_id AND is_primary = 1 LIMIT 1) AS primary_photo
            FROM profiles p
            JOIN users u ON u.id = p.user_id
            WHERE p.gender = :gender
              AND u.last_login_at >= DATE_SUB(NOW(), INTERVAL :min MINUTE)
              AND u.is_active = 1
              AND p.user_id != :me
            ORDER BY u.last_login_at DESC
            LIMIT :lim");
        $stmt->bindValue(':gender', $opposite, \PDO::PARAM_STR);
        $stmt->bindValue(':min', $minutes, \PDO::PARAM_INT);
        $stmt->bindValue(':me', $viewerId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function createNotification(int $userId, string $type, array $payload): void
    {
        $pdo = Connection::pdo();
        $pdo->prepare("INSERT INTO match_notifications (user_id, type, payload) VALUES (:uid, :type, :payload)")
            ->execute([':uid' => $userId, ':type' => $type, ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    }

    private function getProfileById(int $userId): ?array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("SELECT user_id, gender FROM profiles WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch() ?: null;
    }
}
