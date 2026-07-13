<?php
namespace Matrimony\Services;

use Matrimony\Database\Connection;

final class CompatibilityService
{
    private MatchEngine $engine;

    public function __construct()
    {
        $this->engine = new MatchEngine();
    }

    public function getTopMatches(int $viewerId, int $limit = 12): array
    {
        $pdo = Connection::pdo();
        $viewer = $this->loadViewer($viewerId);
        if (!$viewer) return [];

        $preferences = $this->loadPreferences($viewerId);
        $opposite = $viewer['gender'] === 'male' ? 'female' : 'male';

        $stmt = $pdo->prepare("
            SELECT p.user_id, p.first_name, p.last_name, p.date_of_birth, p.gender,
                   p.city, p.state, p.country, p.religion, p.caste, p.mother_tongue,
                   p.education, p.occupation, p.annual_income, p.marital_status,
                   p.height_cm, p.about_me, p.created_at,
                   u.is_verified, u.last_login_at,
                   pa.diet, pa.smoke, pa.drink, pa.body_type, pa.complexion,
                   pl.latitude, pl.longitude, pl.willing_to_relocate,

                   (SELECT path FROM profile_photos WHERE user_id = p.user_id AND is_primary = 1 LIMIT 1) AS primary_photo
            FROM profiles p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN profile_assets pa ON pa.user_id = p.user_id
            LEFT JOIN profile_lifestyle pl ON pl.user_id = p.user_id
            LEFT JOIN profile_horoscope ph ON ph.user_id = p.user_id
            WHERE p.gender = :gender
              AND p.user_id != :me
              AND p.user_id NOT IN (SELECT target_id FROM matches WHERE user_id = :me2 AND status IN ('declined','blocked'))
              AND p.user_id NOT IN (SELECT blocked_id FROM profile_blocks WHERE blocker_id = :me3)
              AND u.is_active = 1
            ORDER BY u.last_login_at DESC
            LIMIT :lim");
        $stmt->bindValue(':gender', $opposite, \PDO::PARAM_STR);
        $stmt->bindValue(':me', $viewerId, \PDO::PARAM_INT);
        $stmt->bindValue(':me2', $viewerId, \PDO::PARAM_INT);
        $stmt->bindValue(':me3', $viewerId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit * 3, \PDO::PARAM_INT);
        $stmt->execute();

        $candidates = $stmt->fetchAll();
        if (empty($candidates)) return [];

        $engine = $this->engine;
        $scored = [];
        foreach ($candidates as $candidate) {
            $score = $engine->compute($viewerId, $viewer, $candidate, $preferences);
            if ($this->hasMutualInterest($viewerId, (int) $candidate['user_id'])) {
                $score = min(100, $score + 2);
            }
            $scored[] = [
                'user_id' => $candidate['user_id'],
                'first_name' => $candidate['first_name'],
                'last_name' => $candidate['last_name'] ?? '',
                'age' => $engine->calcAge($candidate['date_of_birth']),
                'gender' => $candidate['gender'],
                'city' => $candidate['city'],
                'state' => $candidate['state'],
                'country' => $candidate['country'],
                'religion' => $candidate['religion'],
                'caste' => $candidate['caste'],
                'mother_tongue' => $candidate['mother_tongue'],
                'education' => $candidate['education'],
                'occupation' => $candidate['occupation'],
                'annual_income' => $candidate['annual_income'],
                'marital_status' => $candidate['marital_status'],
                'height_cm' => $candidate['height_cm'],
                'about_me' => $candidate['about_me'],
                'primary_photo' => $candidate['primary_photo'],
                'verified' => (bool) $candidate['is_verified'],
                'last_active' => $candidate['last_login_at'],
                'compatibility' => $score,
            ];
        }

        usort($scored, fn($a, $b) => $b['compatibility'] - $a['compatibility']);
        return array_slice($scored, 0, $limit);
    }

    public function computeAndCache(int $viewerId, int $targetId): int
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare("SELECT score FROM match_scores WHERE viewer_id = :v AND target_id = :t AND computed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute([':v' => $viewerId, ':t' => $targetId]);
        $cached = $stmt->fetch();

        if ($cached) {
            return (int) $cached['score'];
        }

        $viewer = $this->loadViewer($viewerId);
        $target = $this->loadTarget($targetId);
        $preferences = $this->loadPreferences($viewerId);

        if (!$viewer || !$target) return 0;

        $score = $this->engine->compute($viewerId, $viewer, $target, $preferences);

        $pdo->prepare("
            INSERT INTO match_scores (viewer_id, target_id, score, computed_at)
            VALUES (:v, :t, :s, NOW())
            ON DUPLICATE KEY UPDATE score = :s2, computed_at = NOW()
        ")->execute([':v' => $viewerId, ':t' => $targetId, ':s' => $score, ':s2' => $score]);

        return $score;
    }

    private function loadViewer(int $userId): ?array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("
            SELECT p.*, u.is_verified, u.last_login_at, pa.diet
            FROM profiles p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN profile_assets pa ON pa.user_id = p.user_id
            WHERE p.user_id = :id");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    private function loadTarget(int $userId): ?array
    {
        return $this->loadViewer($userId);
    }

    private function loadPreferences(int $userId): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("SELECT * FROM profile_preferences WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch() ?: [];
    }

    private function hasMutualInterest(int $viewerId, int $targetId): bool
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("SELECT 1 FROM matches WHERE user_id = :target AND target_id = :viewer AND status IN ('interested','mutual')");
        $stmt->execute([':target' => $targetId, ':viewer' => $viewerId]);
        return (bool) $stmt->fetch();
    }
}
