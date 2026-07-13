<?php
declare(strict_types=1);

use Matrimony\Database\Connection;
use Matrimony\Http\Auth;
use Matrimony\Services\FilterBuilder;

final class FilterController
{
    public function getCount(array $filters, int $viewerId): int
    {
        $pdo = Connection::pdo();
        $viewer = $this->getProfile($viewerId);
        if (!$viewer) return 0;

        $builder = new FilterBuilder();
        $filterData = $builder->build($filters, $viewerId, $viewer['gender']);

        $sql = "SELECT COUNT(*) FROM profiles p JOIN users u ON u.id = p.user_id " . $this->joins($filterData['joins']) . " WHERE " . $filterData['where'];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($filterData['params']);
        return (int) $stmt->fetchColumn();
    }

    public function getPreferences(int $userId): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("SELECT * FROM profile_preferences WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch() ?: [];
    }

    public function savePreferences(int $userId, array $data): bool
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare("SELECT id FROM profile_preferences WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);
        $existing = $stmt->fetch();

        $fields = ['min_age', 'max_age', 'min_height_cm', 'max_height_cm', 'pref_income_min'];
        $jsonFields = ['pref_religion', 'pref_caste', 'pref_education', 'pref_location',
                       'pref_marital_status', 'pref_mother_tongue', 'pref_occupation', 'pref_diet'];

        $params = [':user_id' => $userId];
        $sets = [];

        foreach ($fields as $f) {
            if (isset($data[$f])) {
                $params[":{$f}"] = $data[$f];
                $sets[] = "{$f} = :{$f}";
            }
        }

        foreach ($jsonFields as $f) {
            if (isset($data[$f])) {
                $params[":{$f}"] = is_array($data[$f]) ? json_encode($data[$f], JSON_UNESCAPED_UNICODE) : $data[$f];
                $sets[] = "{$f} = :{$f}";
            }
        }

        if (empty($sets)) return false;
        $setStr = implode(', ', $sets);

        if ($existing) {
            $sql = "UPDATE profile_preferences SET {$setStr} WHERE user_id = :user_id";
        } else {
            $cols = 'user_id, ' . implode(', ', array_map(fn($s) => explode(' = ', $s)[0], $sets));
            $vals = ':user_id, ' . implode(', ', array_map(fn($s) => explode(' = ', $s)[1], $sets));
            $sql = "INSERT INTO profile_preferences ({$cols}) VALUES ({$vals})";
        }

        return $pdo->prepare($sql)->execute($params);
    }

    public function saveSearch(int $userId, string $name, array $filters, bool $alertEnabled): bool
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("INSERT INTO profile_searches (user_id, name, filters_json, alert_enabled) VALUES (:uid, :name, :filters, :alert)");
        return $stmt->execute([
            ':uid' => $userId,
            ':name' => $name,
            ':filters' => json_encode($filters, JSON_UNESCAPED_UNICODE),
            ':alert' => $alertEnabled ? 1 : 0,
        ]);
    }

    public function getSavedSearches(int $userId): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("SELECT id, name, filters_json, alert_enabled, created_at FROM profile_searches WHERE user_id = :uid ORDER BY created_at DESC");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function deleteSearch(int $searchId, int $userId): bool
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("DELETE FROM profile_searches WHERE id = :id AND user_id = :uid");
        return $stmt->execute([':id' => $searchId, ':uid' => $userId]);
    }

    private function getProfile(int $userId): ?array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("SELECT user_id, gender FROM profiles WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    private function joins(array $joins): string
    {
        return implode(' ', array_unique($joins));
    }
}
