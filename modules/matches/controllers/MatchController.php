<?php
declare(strict_types=1);

use Matrimony\Database\Connection;
use Matrimony\Http\Auth;
use Matrimony\Services\FilterBuilder;
use Matrimony\Services\MatchEngine;
use Matrimony\Services\CompatibilityService;

final class MatchController
{
    private FilterBuilder $filterBuilder;
    private MatchEngine $engine;

    private const LIST_SELECT = '
        p.user_id, p.first_name, p.last_name, p.date_of_birth, p.gender,
        p.city, p.state, p.country, p.religion, p.caste, p.mother_tongue,
        p.education, p.occupation, p.annual_income, p.marital_status,
        p.height_cm, p.about_me, p.created_at,
        u.is_verified, u.last_login_at,
        pa.diet, pa.smoke, pa.drink, pa.body_type, pa.complexion,
        pl.latitude, pl.longitude, pl.willing_to_relocate';

    public function __construct()
    {
        $this->filterBuilder = new FilterBuilder();
        $this->engine = new MatchEngine();
    }

    public function list(array $filters, string $sort, int $page, int $perPage, int $viewerId): array
    {
        $pdo = Connection::pdo();
        $viewer = $this->getViewerProfile($viewerId);
        if (!$viewer) {
            return ['data' => [], 'meta' => ['total' => 0, 'page' => $page, 'per_page' => $perPage, 'took_ms' => 0], 'facets' => []];
        }

        $start = microtime(true);
        $filterData = $this->filterBuilder->build($filters, $viewerId, $viewer['gender']);
        $orderBy = $this->filterBuilder->getOrderBy($sort);
        $offset = ($page - 1) * $perPage;

        $joinClause = $this->joinsSql($filterData['joins']);

        $countSql = "SELECT COUNT(*) FROM profiles p JOIN users u ON u.id = p.user_id {$joinClause} WHERE " . $filterData['where'];
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($filterData['params']);
        $total = (int) $countStmt->fetchColumn();

        $dataSql = "
            SELECT " . self::LIST_SELECT . ",
                   (SELECT path FROM profile_photos WHERE user_id = p.user_id AND is_primary = 1 LIMIT 1) AS primary_photo
            FROM profiles p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN profile_assets pa ON pa.user_id = p.user_id
            LEFT JOIN profile_lifestyle pl ON pl.user_id = p.user_id
            LEFT JOIN profile_horoscope ph ON ph.user_id = p.user_id
            {$joinClause}
            WHERE " . $filterData['where'] . "
            ORDER BY {$orderBy}
            LIMIT {$perPage} OFFSET {$offset}";
        $dataStmt = $pdo->prepare($dataSql);
        $dataStmt->execute($filterData['params']);
        $rows = $dataStmt->fetchAll();

        $preferences = $this->loadPreferences($viewerId);
        $engine = $this->engine;
        $data = [];
        foreach ($rows as $row) {
            $score = $engine->compute($viewerId, $viewer, $row, $preferences);
            $data[] = [
                'user_id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'] ?? '',
                'age' => $engine->calcAge($row['date_of_birth']),
                'gender' => $row['gender'],
                'city' => $row['city'],
                'state' => $row['state'],
                'education' => $row['education'],
                'occupation' => $row['occupation'],
                'annual_income' => $row['annual_income'],
                'marital_status' => $row['marital_status'],
                'religion' => $row['religion'],
                'caste' => $row['caste'],
                'mother_tongue' => $row['mother_tongue'],
                'height_cm' => $row['height_cm'],
                'about_me' => $row['about_me'],
                'primary_photo' => $row['primary_photo'],
                'verified' => (bool) $row['is_verified'],
                'last_active' => $row['last_login_at'],
                'compatibility' => $score,
            ];
        }

        $took = (int) ((microtime(true) - $start) * 1000);
        $facets = $this->computeFacets($filters, $viewerId, $viewer['gender']);

        return [
            'data' => $data,
            'meta' => ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'took_ms' => $took],
            'facets' => $facets,
        ];
    }

    public function recentlyJoined(int $limit, int $viewerId): array
    {
        $pdo = Connection::pdo();
        $viewer = $this->getViewerProfile($viewerId);
        if (!$viewer) return [];

        $opposite = FilterBuilder::oppositeGender($viewer['gender']);
        $stmt = $pdo->prepare("
            SELECT p.user_id, p.first_name, p.date_of_birth, p.gender, p.city, p.state, p.religion,
                   u.is_verified, u.last_login_at,
                   (SELECT path FROM profile_photos WHERE user_id = p.user_id AND is_primary = 1 LIMIT 1) AS primary_photo
            FROM profiles p
            JOIN users u ON u.id = p.user_id
            WHERE p.gender = :gender
              AND p.user_id != :me
              AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              AND p.user_id NOT IN (SELECT target_id FROM matches WHERE user_id = :me2 AND status IN ('declined','blocked'))
              AND p.user_id NOT IN (SELECT blocked_id FROM profile_blocks WHERE blocker_id = :me3)
            ORDER BY p.created_at DESC
            LIMIT :lim");
        $stmt->bindValue(':gender', $opposite, \PDO::PARAM_STR);
        $stmt->bindValue(':me', $viewerId, \PDO::PARAM_INT);
        $stmt->bindValue(':me2', $viewerId, \PDO::PARAM_INT);
        $stmt->bindValue(':me3', $viewerId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getProfile(int $targetId, int $viewerId): ?array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("
            SELECT p.user_id, p.first_name, p.last_name, p.date_of_birth, p.gender,
                   p.marital_status, p.religion, p.caste, p.sub_caste, p.mother_tongue,
                   p.height_cm, p.education, p.occupation, p.annual_income,
                   p.city, p.state, p.country, p.about_me,
                   p.has_children, p.created_by,
                   u.is_verified, u.last_login_at,
                   pa.diet, pa.smoke, pa.drink, pa.body_type, pa.complexion,
                   pl.latitude, pl.longitude, pl.willing_to_relocate,
                    ph.rashi, ph.nakshatra,
                   pf.father_occupation, pf.mother_occupation, pf.siblings, pf.family_type, pf.family_values,
                   (SELECT path FROM profile_photos WHERE user_id = p.user_id AND is_primary = 1 LIMIT 1) AS primary_photo,
                   (SELECT COUNT(*) FROM profile_photos WHERE user_id = p.user_id) AS photo_count
            FROM profiles p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN profile_assets pa ON pa.user_id = p.user_id
            LEFT JOIN profile_lifestyle pl ON pl.user_id = p.user_id
            LEFT JOIN profile_horoscope ph ON ph.user_id = p.user_id
            LEFT JOIN profile_family pf ON pf.user_id = p.user_id
            WHERE p.user_id = :id");
        $stmt->execute([':id' => $targetId]);
        $row = $stmt->fetch();
        if (!$row) return null;

        $engine = $this->engine;
        return [
            'user_id' => (int) $row['user_id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'age' => $engine->calcAge($row['date_of_birth']),
            'gender' => $row['gender'],
            'date_of_birth' => $row['date_of_birth'],
            'marital_status' => $row['marital_status'],
            'religion' => $row['religion'],
            'caste' => $row['caste'],
            'sub_caste' => $row['sub_caste'],
            'mother_tongue' => $row['mother_tongue'],
            'height_cm' => $row['height_cm'],
            'education' => $row['education'],
            'occupation' => $row['occupation'],
            'annual_income' => $row['annual_income'],
            'city' => $row['city'],
            'state' => $row['state'],
            'country' => $row['country'],
            'about_me' => $row['about_me'],
            'has_children' => $row['has_children'],
            'created_by' => $row['created_by'],
            'diet' => $row['diet'],
            'smoke' => $row['smoke'],
            'drink' => $row['drink'],
            'body_type' => $row['body_type'],
            'complexion' => $row['complexion'],
            'rashi' => $row['rashi'],
            'nakshatra' => $row['nakshatra'],

            'father_occupation' => $row['father_occupation'],
            'mother_occupation' => $row['mother_occupation'],
            'siblings' => $row['siblings'],
            'family_type' => $row['family_type'],
            'family_values' => $row['family_values'],
            'willing_to_relocate' => (bool) $row['willing_to_relocate'],
            'primary_photo' => $row['primary_photo'],
            'photo_count' => (int) $row['photo_count'],
            'verified' => (bool) $row['is_verified'],
            'last_active' => $row['last_login_at'],
        ];
    }

    private function getViewerProfile(int $userId): ?array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("
            SELECT p.user_id, p.gender, p.date_of_birth, p.religion, p.caste, p.mother_tongue,
                   p.education, p.occupation, p.annual_income, p.city, p.state, p.country,
                   p.marital_status, p.height_cm,
                   u.is_verified, u.last_login_at, pa.diet
            FROM profiles p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN profile_assets pa ON pa.user_id = p.user_id
            WHERE p.user_id = :id");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    private function loadPreferences(int $userId): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("SELECT * FROM profile_preferences WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch() ?: [];
    }

    private function joinsSql(array $joins): string
    {
        if (empty($joins)) return '';
        $seen = [];
        $unique = [];
        foreach ($joins as $join) {
            if (preg_match('/\s+(\w+)\s+ON\s+/i', $join, $m)) {
                $alias = $m[1];
                if (!isset($seen[$alias])) {
                    $seen[$alias] = true;
                    $unique[] = $join;
                }
            } else {
                $unique[] = $join;
            }
        }
        return implode(' ', $unique);
    }

    private function computeFacets(array $filters, int $viewerId, string $viewerGender): array
    {
        $pdo = Connection::pdo();
        $filterData = $this->filterBuilder->build($filters, $viewerId, $viewerGender);
        $joins = $this->joinsSql($filterData['joins']);
        $where = $filterData['where'];
        $params = $filterData['params'];

        $facets = [];

        $stmt = $pdo->prepare("SELECT p.religion, COUNT(*) AS cnt FROM profiles p JOIN users u ON u.id = p.user_id {$joins} WHERE {$where} AND p.religion IS NOT NULL GROUP BY p.religion ORDER BY cnt DESC LIMIT 15");
        $stmt->execute($params);
        $religions = [];
        foreach ($stmt->fetchAll() as $row) {
            $religions[$row['religion']] = (int) $row['cnt'];
        }
        $facets['religion'] = $religions;

        $stmt = $pdo->prepare("SELECT p.city, COUNT(*) AS cnt FROM profiles p JOIN users u ON u.id = p.user_id {$joins} WHERE {$where} AND p.city IS NOT NULL GROUP BY p.city ORDER BY cnt DESC LIMIT 15");
        $stmt->execute($params);
        $cities = [];
        foreach ($stmt->fetchAll() as $row) {
            $cities[$row['city']] = (int) $row['cnt'];
        }
        $facets['city'] = $cities;

        return $facets;
    }
}
