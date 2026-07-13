<?php
namespace Matrimony\Services;

use Matrimony\Database\Connection;
use Matrimony\Http\Auth;

final class ProfileService
{
    private const VALIDATION_RULES = [
        'first_name'       => ['maxlen' => 100, 'trim' => true, 'strip' => true],
        'last_name'        => ['maxlen' => 100, 'trim' => true, 'strip' => true],
        'date_of_birth'    => ['pattern' => '/^\d{4}-\d{2}-\d{2}$/', 'maxlen' => 10],
        'gender'           => ['in' => ['male', 'female', 'other']],
        'marital_status'   => ['in' => ['never_married', 'divorced', 'widowed', 'awaiting_divorce']],
        'religion'         => ['maxlen' => 50, 'trim' => true, 'strip' => true],
        'caste'            => ['maxlen' => 100, 'trim' => true, 'strip' => true],
        'sub_caste'        => ['maxlen' => 100, 'trim' => true, 'strip' => true],
        'mother_tongue'    => ['maxlen' => 50, 'trim' => true, 'strip' => true],
        'height_cm'        => ['numeric' => true, 'min' => 50, 'max' => 280],
        'weight_kg'        => ['numeric' => true, 'min' => 10, 'max' => 350],
        'education'        => ['maxlen' => 200, 'trim' => true, 'strip' => true],
        'institution'      => ['maxlen' => 200, 'trim' => true, 'strip' => true],
        'occupation'       => ['maxlen' => 200, 'trim' => true, 'strip' => true],
        'company'          => ['maxlen' => 200, 'trim' => true, 'strip' => true],
        'annual_income'    => ['maxlen' => 100, 'trim' => true, 'strip' => true],
        'city'             => ['maxlen' => 100, 'trim' => true, 'strip' => true],
        'state'            => ['maxlen' => 100, 'trim' => true, 'strip' => true],
        'country'          => ['maxlen' => 100, 'trim' => true, 'strip' => true],
        'phone'            => ['pattern' => '/^[+]?[\d\s\-()]{7,20}$/', 'maxlen' => 20, 'trim' => true, 'strip' => true],
        'work_location'    => ['maxlen' => 200, 'trim' => true, 'strip' => true],
        'about_me'         => ['maxlen' => 5000, 'trim' => true, 'strip' => true],
        'has_children'     => ['in' => ['yes', 'no', '']],
        'created_by'       => ['in' => ['self', 'parent', 'guardian', 'sibling', 'friend', '']],
        'father_name'      => ['maxlen' => 100, 'trim' => true, 'strip' => true],
        'father_occupation' => ['maxlen' => 200, 'trim' => true, 'strip' => true],
        'mother_name'      => ['maxlen' => 100, 'trim' => true, 'strip' => true],
        'mother_occupation' => ['maxlen' => 200, 'trim' => true, 'strip' => true],
        'brothers_count'   => ['numeric' => true, 'min' => 0, 'max' => 50],
        'sisters_count'    => ['numeric' => true, 'min' => 0, 'max' => 50],
        'family_type'      => ['in' => ['nuclear', 'joint', '']],
        'family_values'    => ['in' => ['traditional', 'moderate', 'liberal', '']],
        'family_income'    => ['maxlen' => 100, 'trim' => true, 'strip' => true],
        'family_origin'    => ['maxlen' => 200, 'trim' => true, 'strip' => true],
        'about_family'     => ['maxlen' => 2000, 'trim' => true, 'strip' => true],
        'diet'             => ['maxlen' => 50, 'trim' => true, 'strip' => true],
        'smoke'            => ['maxlen' => 50, 'trim' => true, 'strip' => true],
        'drink'            => ['maxlen' => 50, 'trim' => true, 'strip' => true],
        'languages_known'  => ['maxlen' => 500, 'trim' => true, 'strip' => true],
        'hobbies'          => ['maxlen' => 1000, 'trim' => true, 'strip' => true],
        'interests'        => ['maxlen' => 1000, 'trim' => true, 'strip' => true],
        'smoking_habits'   => ['maxlen' => 200, 'trim' => true, 'strip' => true],
        'drinking_habits'  => ['maxlen' => 200, 'trim' => true, 'strip' => true],
        'body_type'        => ['maxlen' => 50, 'trim' => true, 'strip' => true],
        'complexion'       => ['maxlen' => 50, 'trim' => true, 'strip' => true],
        'rashi'            => ['maxlen' => 50, 'trim' => true, 'strip' => true],
        'nakshatra'        => ['maxlen' => 50, 'trim' => true, 'strip' => true],
        'time_of_birth'    => ['pattern' => '/^\d{2}:\d{2}(:\d{2})?$/', 'maxlen' => 8],
        'place_of_birth'   => ['maxlen' => 200, 'trim' => true, 'strip' => true],
        'min_age'          => ['numeric' => true, 'min' => 18, 'max' => 100],
        'max_age'          => ['numeric' => true, 'min' => 18, 'max' => 100],
        'min_height_cm'    => ['numeric' => true, 'min' => 50, 'max' => 280],
        'max_height_cm'    => ['numeric' => true, 'min' => 50, 'max' => 280],
        'pref_income_min'  => ['maxlen' => 100, 'trim' => true, 'strip' => true],
        'willing_to_relocate' => ['numeric' => true, 'min' => 0, 'max' => 1],
        'profile_visibility'  => ['numeric' => true, 'min' => 0, 'max' => 1],
        'show_phone'          => ['numeric' => true, 'min' => 0, 'max' => 1],
        'show_email'          => ['numeric' => true, 'min' => 0, 'max' => 1],
        'show_photos'         => ['numeric' => true, 'min' => 0, 'max' => 1],
        'show_online_status'  => ['numeric' => true, 'min' => 0, 'max' => 1],
        'receive_interests'   => ['numeric' => true, 'min' => 0, 'max' => 1],
        'privacy_preset'      => ['in' => ['public', 'members', 'private', '']],
    ];

    private const PROFILE_SELECT_COLS = '
        u.id AS user_id, u.email, u.is_active, u.is_verified, u.last_login_at, u.created_at AS joined_at,
        p.*,
        pa.diet, pa.smoke, pa.smoking_habits, pa.drink, pa.drinking_habits,
        pa.body_type, pa.complexion, pa.languages_known, pa.hobbies, pa.interests,
        pf.father_name, pf.father_occupation, pf.mother_name, pf.mother_occupation,
        pf.brothers_count, pf.sisters_count, pf.family_type, pf.family_values,
        pf.family_income, pf.family_origin, pf.about_family,
        ph.rashi, ph.nakshatra, ph.time_of_birth, ph.place_of_birth,
        pl.latitude, pl.longitude, pl.willing_to_relocate, pl.residency_status';

    public function getFullProfile(int $userId): ?array
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare("SELECT " . self::PROFILE_SELECT_COLS . "
            FROM users u
            JOIN profiles p ON p.user_id = u.id
            LEFT JOIN profile_assets pa ON pa.user_id = u.id
            LEFT JOIN profile_family pf ON pf.user_id = u.id
            LEFT JOIN profile_horoscope ph ON ph.user_id = u.id
            LEFT JOIN profile_lifestyle pl ON pl.user_id = u.id
            WHERE u.id = :id");
        $stmt->execute([':id' => $userId]);
        $profile = $stmt->fetch();

        if (!$profile) return null;

        $stmt = $pdo->prepare("SELECT id, path, is_primary FROM profile_photos WHERE user_id = :uid ORDER BY is_primary DESC, id ASC");
        $stmt->execute([':uid' => $userId]);
        $profile['photos'] = $stmt->fetchAll();

        $profile['primary_photo'] = '';
        foreach ($profile['photos'] as $ph) {
            if ($ph['is_primary']) {
                $profile['primary_photo'] = $ph['path'];
                break;
            }
        }
        if (!$profile['primary_photo'] && !empty($profile['photos'])) {
            $profile['primary_photo'] = $profile['photos'][0]['path'];
        }

        $profile['stats'] = $this->getStats($userId);
        $profile['preferences'] = $this->getPreferences($userId);
        $profile['privacy'] = $this->getPrivacy($userId);

        $stmt = $pdo->prepare("SELECT type, status FROM profile_verifications WHERE user_id = :uid");
        $stmt->execute([':uid' => $userId]);
        $profile['verifications'] = $stmt->fetchAll();

        $stmt = $pdo->prepare("
            SELECT mp.name AS plan_name, mp.code AS plan_code, m.starts_at, m.ends_at, m.status
            FROM memberships m
            JOIN membership_plans mp ON mp.id = m.plan_id
            WHERE m.user_id = :uid AND m.status = 'active'
            ORDER BY m.ends_at DESC LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $profile['membership'] = $stmt->fetch() ?: null;

        $profile['profile_id'] = 'MAT' . date('Y') . str_pad((string) $userId, 6, '0', STR_PAD_LEFT);
        $profile['completion_percentage'] = $this->calculateCompletion($profile);
        $profile['completion_fields'] = $this->getCompletionFields($profile);

        return $profile;
    }

    private function upsertRecord(\PDO $pdo, string $table, array $data, string $userIdKey, int $userId): void
    {
        $sets = [];
        $params = [':uid' => $userId];
        foreach ($data as $col => $val) {
            $sets[] = "{$col} = :{$col}";
            $params[":{$col}"] = $val;
        }
        if (empty($sets)) return;

        $setStr = implode(', ', $sets);
        $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$userIdKey} = :ck LIMIT 1");
        $stmt->execute([':ck' => $userId]);
        $exists = (bool) $stmt->fetchColumn();

        if ($exists) {
            $sql = "UPDATE {$table} SET {$setStr} WHERE {$userIdKey} = :uid";
        } else {
            $cols = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_map(fn($s) => ":{$s}", array_keys($data)));
            $sql = "INSERT INTO {$table} ({$userIdKey}, {$cols}) VALUES (:uid, {$placeholders})";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function updatePersonal(int $userId, array $data): string|true
    {
        $data = $this->sanitize($data);
        $errors = $this->validate($data, [
                'first_name', 'last_name', 'date_of_birth', 'gender', 'marital_status',
                'religion', 'caste', 'sub_caste', 'mother_tongue',
                'height_cm', 'weight_kg', 'education', 'institution', 'occupation', 'company',
                'annual_income', 'city', 'state', 'country', 'phone', 'work_location',
            'about_me', 'has_children', 'created_by',
        ]);
        if (!empty($errors)) {
            $msg = 'Validation failed: ' . implode('; ', array_map(fn($f, $e) => "$f: $e", array_keys($errors), $errors));
            $this->logError('updatePersonal validation failed', ['user_id' => $userId, 'errors' => $errors]);
            return $msg;
        }

        $pdo = Connection::pdo();
        $pdo->beginTransaction();
        try {
            $allowed = [
                'first_name', 'last_name', 'date_of_birth', 'gender', 'marital_status',
                'religion', 'caste', 'sub_caste', 'mother_tongue',
                'height_cm', 'weight_kg', 'education', 'institution', 'occupation', 'company',
            'annual_income', 'city', 'state', 'country', 'phone', 'work_location',
                'about_me', 'has_children', 'created_by',
            ];
            $profileSets = [];
            $profileParams = [':uid' => $userId];
            foreach ($allowed as $f) {
                if (array_key_exists($f, $data)) {
                    if ($this->shouldSkipEmpty($f, $data[$f])) continue;
                    $profileSets[] = "{$f} = :{$f}";
                    $profileParams[":{$f}"] = $data[$f];
                }
            }
            if (!empty($profileSets)) {
                $sql = "UPDATE profiles SET " . implode(', ', $profileSets) . " WHERE user_id = :uid";
                $pdo->prepare($sql)->execute($profileParams);
            }

            if (array_key_exists('willing_to_relocate', $data)) {
                $val = (int) $data['willing_to_relocate'];
                $this->upsertRecord($pdo, 'profile_lifestyle', ['willing_to_relocate' => $val], 'user_id', $userId);
            }

            $assetFields = ['body_type', 'complexion'];
            $assetData = [];
            foreach ($assetFields as $f) {
                if (array_key_exists($f, $data)) {
                    $assetData[$f] = $data[$f];
                }
            }
            if (!empty($assetData)) {
                $this->upsertRecord($pdo, 'profile_assets', $assetData, 'user_id', $userId);
            }

            $pdo->commit();
            $this->logActivity($userId, 'profile.updated', ['section' => 'personal']);
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->logError('updatePersonal failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function updateFamily(int $userId, array $data): string|true
    {
        $data = $this->sanitize($data);
        $errors = $this->validate($data, [
            'father_name', 'father_occupation', 'mother_name', 'mother_occupation',
            'brothers_count', 'sisters_count', 'family_type', 'family_values',
            'family_income', 'family_origin', 'about_family',
        ]);
        if (!empty($errors)) {
            $msg = 'Validation failed: ' . implode('; ', array_map(fn($f, $e) => "$f: $e", array_keys($errors), $errors));
            $this->logError('updateFamily validation failed', ['user_id' => $userId, 'errors' => $errors]);
            return $msg;
        }

        $pdo = Connection::pdo();
        $familyData = [];
        foreach (['father_name', 'father_occupation', 'mother_name', 'mother_occupation',
                     'brothers_count', 'sisters_count', 'family_type', 'family_values',
                     'family_income', 'family_origin', 'about_family'] as $f) {
            if (array_key_exists($f, $data)) {
                if ($this->shouldSkipEmpty($f, $data[$f])) continue;
                $familyData[$f] = $data[$f];
            }
        }
        if (empty($familyData)) return true;

        try {
            $this->upsertRecord($pdo, 'profile_family', $familyData, 'user_id', $userId);
            $this->logActivity($userId, 'profile.updated', ['section' => 'family']);
            return true;
        } catch (\Throwable $e) {
            $this->logError('updateFamily failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function updateLifestyle(int $userId, array $data): string|true
    {
        $data = $this->sanitize($data);
        $errors = $this->validate($data, [
            'diet', 'smoke', 'smoking_habits', 'drink', 'drinking_habits',
            'body_type', 'complexion', 'languages_known', 'hobbies', 'interests',
        ]);
        if (!empty($errors)) {
            $msg = 'Validation failed: ' . implode('; ', array_map(fn($f, $e) => "$f: $e", array_keys($errors), $errors));
            $this->logError('updateLifestyle validation failed', ['user_id' => $userId, 'errors' => $errors]);
            return $msg;
        }

        $pdo = Connection::pdo();
        $allowed = ['diet', 'smoke', 'smoking_habits', 'drink', 'drinking_habits',
                     'body_type', 'complexion', 'languages_known', 'hobbies', 'interests'];

        foreach (['languages_known', 'hobbies', 'interests'] as $jf) {
            if (isset($data[$jf]) && is_array($data[$jf])) {
                $data[$jf] = implode(',', $data[$jf]);
            }
        }

        $assetData = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $assetData[$f] = $data[$f];
            }
        }
        if (empty($assetData)) return true;

        try {
            $this->upsertRecord($pdo, 'profile_assets', $assetData, 'user_id', $userId);
            $this->logActivity($userId, 'profile.updated', ['section' => 'lifestyle']);
            return true;
        } catch (\Throwable $e) {
            $this->logError('updateLifestyle failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function updateHoroscope(int $userId, array $data): string|true
    {
        $data = $this->sanitize($data);
        $errors = $this->validate($data, ['rashi', 'nakshatra', 'time_of_birth', 'place_of_birth']);
        if (!empty($errors)) {
            $msg = 'Validation failed: ' . implode('; ', array_map(fn($f, $e) => "$f: $e", array_keys($errors), $errors));
            $this->logError('updateHoroscope validation failed', ['user_id' => $userId, 'errors' => $errors]);
            return $msg;
        }

        $pdo = Connection::pdo();
        $horoscopeData = [];
        foreach (['rashi', 'nakshatra', 'time_of_birth', 'place_of_birth'] as $f) {
            if (array_key_exists($f, $data)) {
                if ($this->shouldSkipEmpty($f, $data[$f])) continue;
                $horoscopeData[$f] = $data[$f];
            }
        }
        if (empty($horoscopeData)) return true;

        try {
            $this->upsertRecord($pdo, 'profile_horoscope', $horoscopeData, 'user_id', $userId);
            $this->logActivity($userId, 'profile.updated', ['section' => 'horoscope']);
            return true;
        } catch (\Throwable $e) {
            $this->logError('updateHoroscope failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function updatePreferences(int $userId, array $data): string|true
    {
        $data = $this->sanitize($data);
        $errors = $this->validate($data, [
            'min_age', 'max_age', 'min_height_cm', 'max_height_cm', 'pref_income_min',
        ]);
        if (!empty($errors)) {
            $msg = 'Validation failed: ' . implode('; ', array_map(fn($f, $e) => "$f: $e", array_keys($errors), $errors));
            $this->logError('updatePreferences validation failed', ['user_id' => $userId, 'errors' => $errors]);
            return $msg;
        }

        $pdo = Connection::pdo();
        $fields = ['min_age', 'max_age', 'min_height_cm', 'max_height_cm', 'pref_income_min'];
        $jsonFields = ['pref_religion', 'pref_caste', 'pref_education', 'pref_location',
                       'pref_marital_status', 'pref_mother_tongue', 'pref_occupation', 'pref_diet'];

        $prefData = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                if ($this->shouldSkipEmpty($f, $data[$f])) continue;
                $prefData[$f] = $data[$f];
            }
        }
        foreach ($jsonFields as $f) {
            if (array_key_exists($f, $data)) {
                $v = $data[$f];
                if (is_array($v)) {
                    $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                } elseif ($v === null || $v === '') {
                    $v = 'null';
                } else {
                    $v = json_encode((string)$v, JSON_UNESCAPED_UNICODE);
                }
                if ($v === false) $v = 'null';
                $prefData[$f] = $v;
            }
        }
        if (empty($prefData)) return true;

        try {
            $this->upsertRecord($pdo, 'profile_preferences', $prefData, 'user_id', $userId);
            $this->logActivity($userId, 'profile.preferences.updated', []);
            return true;
        } catch (\Throwable $e) {
            $this->logError('updatePreferences failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function getPreferences(int $userId): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("SELECT * FROM profile_preferences WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch() ?: [];
    }

    public function getPrivacy(int $userId): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("SELECT * FROM privacy_settings WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch() ?: [
            'profile_visibility' => 1,
            'show_phone' => 0,
            'show_email' => 0,
            'show_photos' => 1,
            'show_online_status' => 1,
            'receive_interests' => 1,
            'privacy_preset' => 'members',
        ];
    }

    public function updatePrivacy(int $userId, array $data): string|true
    {
        $data = $this->sanitize($data);

        if (isset($data['privacy_preset']) && count($data) === 1) {
            $presets = [
                'public' => [
                    'profile_visibility' => 1, 'show_phone' => 1, 'show_email' => 1,
                    'show_photos' => 1, 'show_online_status' => 1, 'receive_interests' => 1,
                ],
                'members' => [
                    'profile_visibility' => 1, 'show_phone' => 0, 'show_email' => 0,
                    'show_photos' => 1, 'show_online_status' => 1, 'receive_interests' => 1,
                ],
                'private' => [
                    'profile_visibility' => 0, 'show_phone' => 0, 'show_email' => 0,
                    'show_photos' => 0, 'show_online_status' => 0, 'receive_interests' => 0,
                ],
            ];
            $preset = $data['privacy_preset'];
            if (isset($presets[$preset])) {
                $data = array_merge($presets[$preset], ['privacy_preset' => $preset]);
            }
        }

        $errors = $this->validate($data, [
            'profile_visibility', 'show_phone', 'show_email', 'show_photos',
            'show_online_status', 'receive_interests', 'privacy_preset',
        ]);
        if (!empty($errors)) {
            $msg = 'Validation failed: ' . implode('; ', array_map(fn($f, $e) => "$f: $e", array_keys($errors), $errors));
            $this->logError('updatePrivacy validation failed', ['user_id' => $userId, 'errors' => $errors]);
            return $msg;
        }

        $pdo = Connection::pdo();
        $privacyData = [];
        foreach (['profile_visibility', 'show_phone', 'show_email', 'show_photos',
                     'show_online_status', 'receive_interests', 'privacy_preset'] as $f) {
            if (array_key_exists($f, $data)) {
                if (in_array($f, ['profile_visibility','show_phone','show_email','show_photos','show_online_status','receive_interests'])) {
                    $privacyData[$f] = $data[$f] ? 1 : 0;
                } else {
                    $privacyData[$f] = $data[$f];
                }
            }
        }
        if (empty($privacyData)) return false;

        try {
            $this->upsertRecord($pdo, 'privacy_settings', $privacyData, 'user_id', $userId);
            return true;
        } catch (\Throwable $e) {
            $this->logError('updatePrivacy failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function getStats(int $userId): array
    {
        $pdo = Connection::pdo();
        $sql = "SELECT
            (SELECT COUNT(*) FROM profile_views WHERE profile_id = :uid1) AS profile_views,
            (SELECT COUNT(*) FROM matches WHERE target_id = :uid2 AND status IN ('interested','mutual')) AS interests_received,
            (SELECT COUNT(*) FROM matches WHERE target_id = :uid3 AND status = 'mutual') AS mutual_matches,
            (SELECT COUNT(*) FROM matches WHERE target_id = :uid4 AND status = 'shortlisted') AS shortlists";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid1' => $userId, ':uid2' => $userId, ':uid3' => $userId, ':uid4' => $userId]);
        $row = $stmt->fetch();
        return [
            'profile_views' => (int) ($row['profile_views'] ?? 0),
            'interests_received' => (int) ($row['interests_received'] ?? 0),
            'mutual_matches' => (int) ($row['mutual_matches'] ?? 0),
            'shortlists' => (int) ($row['shortlists'] ?? 0),
        ];
    }

    public function calculateCompletion(array $profile): int
    {
        $points = 0;
        $total = 0;

        $checks = [
            'personal' => ['first_name', 'last_name', 'gender', 'date_of_birth', 'marital_status', 'religion', 'mother_tongue', 'height_cm', 'education', 'occupation', 'city', 'about_me'],
            'professional' => ['institution', 'company', 'annual_income'],
        ];

        foreach ($checks['personal'] as $f) { $total += 6; if (!empty($profile[$f])) $points += 6; }
        foreach ($checks['professional'] as $f) { $total += 4; if (!empty($profile[$f])) $points += 4; }

        $total += 10;
        if (!empty($profile['primary_photo'])) $points += 10;

        $familyFields = ['father_name', 'mother_name', 'family_type', 'family_values'];
        $total += 8;
        $filled = 0;
        foreach ($familyFields as $f) { if (!empty($profile[$f])) $filled++; }
        $points += $filled > 0 ? min(8, $filled * 2) : 0;

        $total += 10;
        $prefs = $profile['preferences'] ?? [];
        if (!empty($prefs)) {
            $prefFilled = 0;
            foreach (['min_age','max_age','pref_religion','pref_caste'] as $pf) {
                if (!empty($prefs[$pf])) $prefFilled++;
            }
            $points += $prefFilled > 0 ? min(10, $prefFilled * 2.5) : 0;
        }

        return (int) min(100, round(($points / max($total, 1)) * 100));
    }

    public function getCompletionFields(array $profile): array
    {
        $fields = [];
        $checks = [
            'Basic Info' => ['first_name' => 'First Name', 'last_name' => 'Last Name', 'gender' => 'Gender', 'date_of_birth' => 'Date of Birth', 'marital_status' => 'Marital Status'],
            'Religion & Culture' => ['religion' => 'Religion', 'mother_tongue' => 'Mother Tongue', 'caste' => 'Caste'],
            'Education & Career' => ['education' => 'Education', 'occupation' => 'Occupation', 'annual_income' => 'Annual Income'],
            'Location' => ['city' => 'City', 'state' => 'State'],
            'About' => ['about_me' => 'About Me'],
            'Photo' => ['primary_photo' => 'Profile Photo'],
        ];
        foreach ($checks as $group => $items) {
            $allDone = true;
            $missing = [];
            foreach ($items as $key => $label) {
                $done = !empty($profile[$key]);
                if (!$done) { $allDone = false; $missing[] = $label; }
            }
            $fields[] = ['group' => $group, 'done' => $allDone, 'missing' => $missing];
        }
        return $fields;
    }

    public function addPhoto(int $userId, array $file): ?array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) return null;

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowedMimes, true)) return null;

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($ext, $allowedExt)) return null;

        $maxSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxSize) return null;

        if (!@getimagesize($file['tmp_name'])) return null;

        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM profile_photos WHERE user_id = :uid");
        $stmt->execute([':uid' => $userId]);
        if ((int) $stmt->fetchColumn() >= 20) return null;

        $dir = BASE_PATH . '/public_html/uploads/' . $userId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = 'photo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $this->logError('move_uploaded_file failed', ['user_id' => $userId, 'tmp' => $file['tmp_name'], 'dest' => $dest]);
            return null;
        }

        $path = '/uploads/' . $userId . '/' . $filename;

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM profile_photos WHERE user_id = :uid");
            $stmt->execute([':uid' => $userId]);
            $isPrimary = (int) $stmt->fetchColumn() === 0 ? 1 : 0;

            $stmt = $pdo->prepare("INSERT INTO profile_photos (user_id, path, is_primary, status) VALUES (:uid, :path, :pri, 'approved')");
            $stmt->execute([':uid' => $userId, ':path' => $path, ':pri' => $isPrimary]);
            $photoId = (int) $pdo->lastInsertId();

            $pdo->commit();
            $this->logActivity($userId, 'photo.uploaded', ['photo_id' => $photoId]);
            return ['id' => $photoId, 'path' => $path, 'is_primary' => (bool) $isPrimary];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            @unlink($dest);
            $this->logError('addPhoto transaction failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function deletePhoto(int $userId, int $photoId): bool
    {
        $pdo = Connection::pdo();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT path, is_primary FROM profile_photos WHERE id = :id AND user_id = :uid FOR UPDATE");
            $stmt->execute([':id' => $photoId, ':uid' => $userId]);
            $photo = $stmt->fetch();
            if (!$photo) {
                $pdo->rollBack();
                return false;
            }

            $file = BASE_PATH . '/public_html' . $photo['path'];
            if (is_file($file)) unlink($file);

            $stmt = $pdo->prepare("DELETE FROM profile_photos WHERE id = :id");
            $stmt->execute([':id' => $photoId]);

            if ($photo['is_primary']) {
                $stmt = $pdo->prepare("SELECT id FROM profile_photos WHERE user_id = :uid ORDER BY id ASC LIMIT 1");
                $stmt->execute([':uid' => $userId]);
                $next = $stmt->fetch();
                if ($next) {
                    $pdo->prepare("UPDATE profile_photos SET is_primary = 1 WHERE id = :id")->execute([':id' => $next['id']]);
                }
            }

            $pdo->commit();
            $this->logActivity($userId, 'photo.deleted', ['photo_id' => $photoId]);
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->logError('deletePhoto failed', ['user_id' => $userId, 'photo_id' => $photoId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function setPrimaryPhoto(int $userId, int $photoId): bool
    {
        $pdo = Connection::pdo();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id FROM profile_photos WHERE id = :id AND user_id = :uid FOR UPDATE");
            $stmt->execute([':id' => $photoId, ':uid' => $userId]);
            if (!$stmt->fetch()) {
                $pdo->rollBack();
                return false;
            }
            $pdo->prepare("UPDATE profile_photos SET is_primary = 0 WHERE user_id = :uid")->execute([':uid' => $userId]);
            $pdo->prepare("UPDATE profile_photos SET is_primary = 1 WHERE id = :id")->execute([':id' => $photoId]);
            $pdo->commit();
            $this->logActivity($userId, 'photo.primary', ['photo_id' => $photoId]);
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->logError('setPrimaryPhoto failed', ['user_id' => $userId, 'photo_id' => $photoId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function getActivity(int $userId, int $limit = 20): array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("
            SELECT action, details, created_at FROM activity_log WHERE user_id = :uid
            ORDER BY created_at DESC LIMIT :lim");
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getViewerProfile(int $userId): ?array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("
            SELECT u.id AS user_id, u.is_verified, u.last_login_at,
                   p.first_name, p.last_name, p.gender, p.date_of_birth,
                   p.marital_status, p.religion, p.caste, p.sub_caste, p.mother_tongue,
                   p.height_cm, p.weight_kg, p.education, p.institution, p.occupation, p.company,
                   p.annual_income, p.city, p.state, p.country, p.about_me,
                   p.has_children, p.created_by, p.work_location,
                   pa.diet, pa.body_type, pa.complexion, pa.smoking_habits, pa.drinking_habits,
                   pa.languages_known, pa.hobbies, pa.interests,
                   pf.father_name, pf.father_occupation, pf.mother_name, pf.mother_occupation,
                   pf.brothers_count, pf.sisters_count, pf.family_type, pf.family_values,
                   pf.family_income, pf.family_origin, pf.about_family,
                    ph.rashi, ph.nakshatra,
                   (SELECT path FROM profile_photos WHERE user_id = p.user_id AND is_primary = 1 LIMIT 1) AS primary_photo
            FROM users u
            JOIN profiles p ON p.user_id = u.id
            LEFT JOIN profile_assets pa ON pa.user_id = u.id
            LEFT JOIN profile_family pf ON pf.user_id = u.id
            LEFT JOIN profile_horoscope ph ON ph.user_id = u.id
            WHERE u.id = :id AND u.is_active = 1");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function calcAge(string $dob): int
    {
        if (empty($dob) || $dob === '0000-00-00') return 0;
        try {
            $birth = new \DateTime($dob);
            $now = new \DateTime();
            return (int) $now->diff($birth)->y;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function shouldSkipEmpty(string $field, mixed $value): bool
    {
        if ($value !== '') return false;
        $rules = self::VALIDATION_RULES[$field] ?? [];
        return isset($rules['in']) || isset($rules['numeric']) || isset($rules['pattern']);
    }

    private function sanitize(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (!is_string($value) && !is_numeric($value) && !is_null($value) && !is_array($value)) continue;
            if (is_string($value)) {
                $value = trim($value);
                $value = preg_replace('/\s+/', ' ', $value);
            }
            $out[$key] = $value;
        }
        return $out;
    }

    private function validate(array $data, array $allowedFields): array
    {
        $errors = [];
        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $data)) continue;
            $value = $data[$field];
            $rules = self::VALIDATION_RULES[$field] ?? [];

            if (isset($rules['in']) && $value !== '' && !in_array($value, $rules['in'], true)) {
                $errors[$field] = 'Invalid value';
                continue;
            }

            if (isset($rules['numeric']) && $rules['numeric'] && $value !== '' && $value !== null) {
                if (!is_numeric($value)) {
                    $errors[$field] = 'Must be numeric';
                    continue;
                }
                $num = (float) $value;
                if (isset($rules['min']) && $num < $rules['min']) {
                    $errors[$field] = 'Minimum ' . $rules['min'];
                    continue;
                }
                if (isset($rules['max']) && $num > $rules['max']) {
                    $errors[$field] = 'Maximum ' . $rules['max'];
                    continue;
                }
            }

            if (isset($rules['pattern']) && $value !== '' && $value !== null) {
                if (!preg_match($rules['pattern'], (string) $value)) {
                    $errors[$field] = 'Invalid format';
                    continue;
                }
            }

            if (isset($rules['maxlen']) && is_string($value) && strlen($value) > $rules['maxlen']) {
                $errors[$field] = 'Maximum ' . $rules['maxlen'] . ' characters';
                continue;
            }
        }
        return $errors;
    }

    private function logActivity(int $userId, string $action, array $details = []): void
    {
        try {
            $pdo = Connection::pdo();
            $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (:uid, :act, :det)");
            $stmt->execute([':uid' => $userId, ':act' => $action, ':det' => json_encode($details, JSON_UNESCAPED_UNICODE)]);
        } catch (\Throwable $e) {
            error_log('ProfileService::logActivity error: ' . $e->getMessage());
        }
    }

    private function logError(string $message, array $context = []): void
    {
        error_log('ProfileService: ' . $message . ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE));
    }
}
