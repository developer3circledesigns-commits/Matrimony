<?php
declare(strict_types=1);

namespace Matrimony\Services;

use Matrimony\Database\Connection;
use Matrimony\Http\Authorization;
use Matrimony\Http\Auth;
use Matrimony\Security\AuditService;
use Matrimony\Security\Validation;

/**
 * Admin-side user management: listing plus the full create / read / update /
 * delete lifecycle.
 *
 * Deletion is always SOFT (account_status = 'deleted'). The users table has no
 * hard-delete convention, audit rows reference user IDs, and purchases,
 * memberships and outbox entries all point at a user — a hard delete would
 * either fail on a foreign key or silently orphan financial records.
 */
final class AdminUserService
{
    /** Roles that grant privileged access; changing these needs a super admin. */
    public const PRIVILEGED_ROLES = ['admin', 'super_admin'];

    /** Statuses an operator may set directly (delete/restore are separate ops). */
    public const SETTABLE_STATUSES = ['pending_verification', 'active', 'suspended', 'deactivated'];

    public const GENDERS = ['male', 'female', 'other'];
    public const MARITAL_STATUSES = ['never_married', 'divorced', 'widowed', 'awaiting_divorce'];
    public const CREATED_BY = ['self', 'parent', 'guardian', 'sibling', 'friend'];
    public const HAS_CHILDREN = ['yes', 'no'];

    /** Columns of the `users` table the admin forms own. */
    public const ACCOUNT_FIELDS = [
        'email', 'email_normalized', 'phone', 'phone_normalized',
        'role', 'account_status', 'is_active', 'is_verified',
    ];

    /** Columns of the `profiles` table the admin forms own. */
    public const PROFILE_FIELDS = [
        'first_name', 'last_name', 'gender', 'date_of_birth', 'marital_status',
        'religion', 'caste', 'sub_caste', 'mother_tongue', 'has_children',
        'height_cm', 'weight_kg', 'education', 'institution', 'occupation',
        'company', 'annual_income', 'city', 'state', 'country', 'work_location',
        'created_by', 'created_by_name', 'about_me',
    ];

    /** Columns of `profile_family` the admin forms own. */
    public const FAMILY_FIELDS = [
        'father_name', 'father_occupation', 'mother_name', 'mother_occupation',
        'brothers_count', 'sisters_count', 'siblings', 'family_type',
        'family_values', 'family_income', 'family_origin', 'about_family',
    ];

    /** Columns of `profile_assets` (habits / appearance) the forms own. */
    public const ASSET_FIELDS = [
        'diet', 'smoke', 'smoking_habits', 'drink', 'drinking_habits',
        'body_type', 'complexion', 'languages_known', 'hobbies', 'interests', 'pet',
    ];

    /** Columns of `profile_horoscope` the admin forms own. */
    public const HOROSCOPE_FIELDS = ['rashi', 'nakshatra', 'time_of_birth', 'place_of_birth'];

    /** Columns of `profile_lifestyle` the admin forms own. */
    public const LIFESTYLE_FIELDS = ['latitude', 'longitude', 'willing_to_relocate', 'residency_status'];

    /** Columns of `profile_preferences` the admin forms own. */
    public const PREFERENCE_FIELDS = [
        'min_age', 'max_age', 'min_height_cm', 'max_height_cm', 'pref_income_min',
        'pref_religion', 'pref_caste', 'pref_education', 'pref_location',
        'pref_marital_status', 'pref_mother_tongue', 'pref_occupation', 'pref_diet',
    ];

    /** Preference columns stored as JSON arrays of strings. */
    public const PREFERENCE_JSON_FIELDS = [
        'pref_religion', 'pref_caste', 'pref_education', 'pref_location',
        'pref_marital_status', 'pref_mother_tongue', 'pref_occupation', 'pref_diet',
    ];

    /** Columns of `privacy_settings` the admin forms own. */
    public const PRIVACY_FIELDS = [
        'profile_visibility', 'show_phone', 'show_email', 'show_photos',
        'show_online_status', 'receive_interests', 'allow_contact_reveal', 'privacy_preset',
    ];

    /**
     * Related profile tables and the columns each owns, so the CRUD path can
     * upsert them generically. Table names are literals here, never user input.
     *
     * @var array<string, string[]>
     */
    private const RELATED_TABLES = [
        'profile_family' => self::FAMILY_FIELDS,
        'profile_assets' => self::ASSET_FIELDS,
        'profile_horoscope' => self::HOROSCOPE_FIELDS,
        'profile_lifestyle' => self::LIFESTYLE_FIELDS,
        'profile_preferences' => self::PREFERENCE_FIELDS,
        'privacy_settings' => self::PRIVACY_FIELDS,
    ];

    private const PASSWORD_MIN = 10;

    // ---------------------------------------------------------------- list

    public static function listUsers(array $f): array
    {
        $pdo = Connection::pdo();
        $where = ['1=1'];
        $params = [];
        if (!empty($f['q'])) {
            $where[] = '(u.email LIKE :q1 OR p.first_name LIKE :q2 OR p.last_name LIKE :q3 OR p.city LIKE :q4 OR u.phone LIKE :q5)';
            $like = '%' . substr($f['q'], 0, 80) . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
            $params[':q5'] = $like;
        }
        if (!empty($f['role'])) {
            $where[] = 'u.role = :r';
            $params[':r'] = $f['role'];
        }
        if (!empty($f['status'])) {
            $where[] = 'u.account_status = :s';
            $params[':s'] = $f['status'];
        }
        // NOTE: `!empty()` would swallow the string '0', which is exactly what
        // the "Missing profile" filter sends.
        if (isset($f['verified']) && in_array((string) $f['verified'], ['1', '0'], true)) {
            $where[] = (string) $f['verified'] === '1' ? 'u.is_verified = 1' : 'u.is_verified = 0';
        }
        if (isset($f['has_profile']) && in_array((string) $f['has_profile'], ['1', '0'], true)) {
            $where[] = (string) $f['has_profile'] === '1'
                ? 'p.user_id IS NOT NULL'
                : 'p.user_id IS NULL';
        }

        $page = max(1, (int) ($f['page'] ?? 1));
        $per = min(100, max(10, (int) ($f['per'] ?? 25)));
        $off = ($page - 1) * $per;
        $w = implode(' AND ', $where);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN profiles p ON p.user_id = u.id WHERE $w");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $sort = Validation::sortKey($f['sort'] ?? null, ['newest', 'oldest', 'email', 'status', 'name', 'last_login'], 'newest');
        $order = [
            'email' => 'u.email ASC',
            'status' => 'u.account_status ASC, u.id DESC',
            'oldest' => 'u.id ASC',
            'name' => 'p.first_name ASC, p.last_name ASC, u.id DESC',
            'last_login' => 'u.last_login_at IS NULL, u.last_login_at DESC',
        ][$sort] ?? 'u.id DESC';

        $stmt = $pdo->prepare(
            "SELECT u.id, u.email, u.phone, u.role, u.account_status, u.is_active, u.is_verified,
                    u.last_login_at, u.created_at,
                    p.id AS profile_id, p.first_name, p.last_name, p.city, p.state, p.gender, p.moderation_status
             FROM users u
             LEFT JOIN profiles p ON p.user_id = u.id
             WHERE {$w}
             ORDER BY {$order}
             LIMIT {$per} OFFSET {$off}"
        );
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per' => $per];
    }

    /** Field-level counts used to build the filter bar. */
    public static function statusCounts(): array
    {
        try {
            $pdo = Connection::pdo();
            $rows = $pdo->query(
                'SELECT account_status, COUNT(*) c FROM users GROUP BY account_status'
            )->fetchAll();
            $out = [];
            foreach ($rows as $r) {
                $out[(string) $r['account_status']] = (int) $r['c'];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Load one user plus its profile and every related profile table, or null. */
    public static function find(int $id): ?array
    {
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare(
            'SELECT u.*, p.id AS profile_id, p.first_name, p.last_name, p.gender, p.date_of_birth,
                    p.marital_status, p.religion, p.caste, p.sub_caste, p.mother_tongue,
                    p.has_children, p.height_cm, p.weight_kg, p.education, p.institution,
                    p.occupation, p.company, p.annual_income, p.city, p.state, p.country,
                    p.work_location, p.phone AS profile_phone, p.created_by, p.created_by_name,
                    p.about_me, p.moderation_status, p.is_hidden,
                    pf.father_name, pf.father_occupation, pf.mother_name, pf.mother_occupation,
                    pf.brothers_count, pf.sisters_count, pf.siblings, pf.family_type,
                    pf.family_values, pf.family_income, pf.family_origin, pf.about_family,
                    pa.diet, pa.smoke, pa.smoking_habits, pa.drink, pa.drinking_habits,
                    pa.body_type, pa.complexion, pa.languages_known, pa.hobbies, pa.interests, pa.pet,
                    ph.rashi, ph.nakshatra, ph.time_of_birth, ph.place_of_birth,
                    (ph.kattam_mime IS NOT NULL) AS has_kattam, ph.kattam_updated_at,
                    pl.latitude, pl.longitude, pl.willing_to_relocate, pl.residency_status,
                    ps.profile_visibility, ps.show_phone, ps.show_email, ps.show_photos,
                    ps.show_online_status, ps.receive_interests, ps.allow_contact_reveal,
                    ps.privacy_preset,
                    pp.min_age, pp.max_age, pp.min_height_cm, pp.max_height_cm, pp.pref_income_min,
                    pp.pref_religion, pp.pref_caste, pp.pref_education, pp.pref_location,
                    pp.pref_marital_status, pp.pref_mother_tongue, pp.pref_occupation, pp.pref_diet,
                    (SELECT path FROM profile_photos WHERE user_id = u.id ORDER BY is_primary DESC, id ASC LIMIT 1) AS primary_photo
             FROM users u
             LEFT JOIN profiles p ON p.user_id = u.id
             LEFT JOIN profile_family pf ON pf.user_id = u.id
             LEFT JOIN profile_assets pa ON pa.user_id = u.id
             LEFT JOIN profile_horoscope ph ON ph.user_id = u.id
             LEFT JOIN profile_lifestyle pl ON pl.user_id = u.id
             LEFT JOIN privacy_settings ps ON ps.user_id = u.id
             LEFT JOIN profile_preferences pp ON pp.user_id = u.id
             WHERE u.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        // Present JSON preference arrays as comma-separated text the form can show.
        foreach (self::PREFERENCE_JSON_FIELDS as $field) {
            $row[$field] = self::decodePrefList($row[$field] ?? null);
        }
        unset($row['password_hash'], $row['totp_secret'], $row['must_reauth_at']);
        return $row;
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        $pdo = Connection::pdo();
        $sql = 'SELECT id FROM users WHERE (email_normalized = :e OR LOWER(email) = :e2)';
        $params = [':e' => strtolower(trim($email)), ':e2' => strtolower(trim($email))];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $exceptId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }

    // ------------------------------------------------------------ validate

    /**
     * Normalise + validate a user form submission.
     *
     * @param  array $in       raw $_POST
     * @param  bool  $isCreate whether a password is mandatory
     * @return array{data:array,errors:array<string,string>,password:string,provided:array}
     */
    public static function validate(array $in, bool $isCreate, int $adminId, bool $isSuper, int $subjectId = 0): array
    {
        $errors = [];
        $data = [];

        /*
         * On update the form may submit only a subset of controls, so a
         * required check must not fire for a field that was never sent —
         * otherwise every partial save is rejected for a missing date of
         * birth it never intended to touch.
         */
        $mustCheck = static fn(string $key): bool => $isCreate || array_key_exists($key, $in);

        // ---- account ----
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        if ($email === '') {
            if ($mustCheck('email')) {
                $errors['email'] = 'Email address is required.';
            }
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (mb_strlen($email) > 190) {
            $errors['email'] = 'Email address is too long.';
        } else {
            $changedEmail = $subjectId > 0
                && strtolower((string) self::rawField($subjectId, 'email')) !== $email;
            if ($changedEmail && self::emailExists($email, $subjectId)) {
                $errors['email'] = 'Another account already uses that email address.';
            } elseif ($isCreate && self::emailExists($email, null)) {
                $errors['email'] = 'An account with that email already exists.';
            }
            $data['email'] = $email;
        }

        if (array_key_exists('phone', $in)) {
            $phone = trim((string) $in['phone']);
            if ($phone === '') {
                $data['phone'] = null;
                $data['phone_normalized'] = null;
            } else {
                $normalised = Validation::phone($phone);
                if ($normalised === null) {
                    $errors['phone'] = 'Enter a valid phone number (7–15 digits).';
                } else {
                    $data['phone_normalized'] = $normalised;
                    $data['phone'] = $phone;
                }
            }
        }

        if (array_key_exists('role', $in) || $isCreate) {
            $role = (string) ($in['role'] ?? 'user');
            if (!in_array($role, ['user', 'moderator', 'admin', 'super_admin'], true)) {
                $errors['role'] = 'Choose a valid role.';
            } elseif (!$isSuper && in_array($role, self::PRIVILEGED_ROLES, true)) {
                $errors['role'] = 'Only a Super Admin can grant admin or super admin access.';
            } elseif ($subjectId > 0 && $subjectId === $adminId && $role !== 'super_admin') {
                $current = (string) self::rawField($subjectId, 'role');
                if (in_array($current, self::PRIVILEGED_ROLES, true)) {
                    $errors['role'] = 'You cannot remove your own privileged role.';
                }
            }
            $data['role'] = $role;
        }

        if (array_key_exists('account_status', $in) || $isCreate) {
            $status = (string) ($in['account_status'] ?? 'active');
            if (!in_array($status, self::SETTABLE_STATUSES, true)) {
                $errors['account_status'] = 'Choose a valid account status.';
            } elseif ($subjectId > 0 && $subjectId === $adminId && $status !== 'active') {
                $errors['account_status'] = 'You cannot change your own account status.';
            }
            $data['account_status'] = $status;
            $data['is_active'] = in_array($status, ['active', 'pending_verification'], true) ? 1 : 0;
        }

        if (array_key_exists('is_verified', $in)) {
            // Verification state is a privileged change — only Super Admins may set it.
            if (!$isSuper && !empty($in['is_verified'])) {
                $errors['is_verified'] = 'Only a Super Admin can verify accounts.';
            } else {
                $data['is_verified'] = !empty($in['is_verified']) ? 1 : 0;
            }
        }

        $password = (string) ($in['password'] ?? '');
        if ($isCreate || $password !== '') {
            $errors += self::validatePassword($password);
        }

        // ---- identity ----
        $firstName = trim((string) ($in['first_name'] ?? ''));
        if ($firstName === '') {
            if ($mustCheck('first_name')) {
                $errors['first_name'] = 'First name is required.';
            }
        } elseif (mb_strlen($firstName) > 80) {
            $errors['first_name'] = 'First name must be 80 characters or fewer.';
        }
        if ($firstName !== '' || $mustCheck('first_name')) {
            $data['first_name'] = $firstName;
        }

        $lastNameRaw = trim((string) ($in['last_name'] ?? ''));
        $lastName = $lastNameRaw !== '' ? $lastNameRaw : $firstName;
        if (mb_strlen($lastName) > 80) {
            $errors['last_name'] = 'Last name must be 80 characters or fewer.';
        }
        if (array_key_exists('last_name', $in) || $firstName !== '') {
            $data['last_name'] = $lastName === '' ? $firstName : $lastName;
        }

        $gender = (string) ($in['gender'] ?? '');
        if ($gender === '') {
            if ($mustCheck('gender')) {
                $errors['gender'] = 'Gender is required.';
            }
        } elseif (!in_array($gender, self::GENDERS, true)) {
            $errors['gender'] = 'Choose a valid gender.';
        } else {
            $data['gender'] = $gender;
        }

        if (array_key_exists('date_of_birth', $in)) {
            $dob = trim((string) $in['date_of_birth']);
            if ($dob === '') {
                if ($mustCheck('date_of_birth')) {
                    $errors['date_of_birth'] = 'Date of birth is required.';
                }
            } else {
                $parsed = date_create_immutable($dob);
                if ($parsed === false) {
                    $errors['date_of_birth'] = 'Enter a valid date.';
                } else {
                    $age = self::ageOn($parsed, new \DateTimeImmutable('today'));
                    if ($age < 18) {
                        $errors['date_of_birth'] = 'Members must be at least 18 years old.';
                    } elseif ($age > 100) {
                        $errors['date_of_birth'] = 'Enter a date of birth within the last 100 years.';
                    } else {
                        $data['date_of_birth'] = $parsed->format('Y-m-d');
                    }
                }
            }
        }

        if (array_key_exists('marital_status', $in)) {
            $data['marital_status'] = Validation::enum($in['marital_status'], self::MARITAL_STATUSES, 'never_married');
        }
        if (array_key_exists('has_children', $in)) {
            $data['has_children'] = Validation::enum($in['has_children'], self::HAS_CHILDREN, null);
        }
        if (array_key_exists('created_by', $in)) {
            $data['created_by'] = Validation::enum($in['created_by'], self::CREATED_BY, 'self');
        } elseif ($isCreate) {
            // profiles.created_by is NOT NULL; an explicit NULL would defeat the
            // column default and reject the whole insert.
            $data['created_by'] = 'self';
        }
        if (array_key_exists('created_by_name', $in)) {
            $data['created_by_name'] = Validation::text((string) $in['created_by_name'], 80) ?: null;
        }

        if (array_key_exists('height_cm', $in) || array_key_exists('weight_kg', $in)) {
            foreach (['height_cm' => [90, 250], 'weight_kg' => [25, 250]] as $field => [$min, $max]) {
                if (!array_key_exists($field, $in)) {
                    continue;
                }
                $raw = trim((string) $in[$field]);
                if ($raw === '') {
                    $data[$field] = null;
                    continue;
                }
                $v = Validation::intRange($raw, $min, $max);
                if ($v === null) {
                    $errors[$field] = sprintf(
                        '%s must be between %d and %d.',
                        $field === 'height_cm' ? 'Height' : 'Weight',
                        $min,
                        $max
                    );
                } else {
                    $data[$field] = $v;
                }
            }
        }

        // ---- lifestyle / location / about ----
        $short = [
            'religion' => 60, 'caste' => 80, 'sub_caste' => 80, 'mother_tongue' => 60,
            'education' => 120, 'institution' => 120, 'occupation' => 120, 'company' => 120,
            'annual_income' => 60, 'city' => 80, 'state' => 80, 'country' => 80, 'work_location' => 120,
        ];
        foreach ($short as $field => $max) {
            if (array_key_exists($field, $in)) {
                $data[$field] = Validation::text((string) $in[$field], $max) ?: null;
            }
        }
        if (array_key_exists('about_me', $in)) {
            $data['about_me'] = Validation::text((string) $in['about_me'], 2000) ?: null;
        }

        // ---- family ----
        foreach ([
            'father_name' => 100, 'father_occupation' => 120, 'mother_name' => 100,
            'mother_occupation' => 120, 'family_income' => 50, 'family_origin' => 100,
            'siblings' => 30,
        ] as $field => $max) {
            if (array_key_exists($field, $in)) {
                $data[$field] = Validation::text((string) $in[$field], $max) ?: null;
            }
        }
        if (array_key_exists('about_family', $in)) {
            $data['about_family'] = Validation::text((string) $in['about_family'], 2000) ?: null;
        }
        foreach (['brothers_count' => 'Brothers', 'sisters_count' => 'Sisters'] as $field => $label) {
            if (!array_key_exists($field, $in)) {
                continue;
            }
            $raw = trim((string) $in[$field]);
            if ($raw === '') {
                $data[$field] = null;
                continue;
            }
            $v = Validation::intRange($raw, 0, 50);
            if ($v === null) {
                $errors[$field] = $label . ' must be a whole number between 0 and 50.';
            } else {
                $data[$field] = $v;
            }
        }
        if (array_key_exists('family_type', $in)) {
            $data['family_type'] = Validation::enum($in['family_type'] ?? null, ['joint', 'nuclear'], null);
        }
        if (array_key_exists('family_values', $in)) {
            $data['family_values'] = Validation::enum($in['family_values'] ?? null, ['traditional', 'moderate', 'liberal'], null);
        }

        // ---- habits & appearance (profile_assets) ----
        foreach ([
            'diet' => 30, 'smoke' => 30, 'smoking_habits' => 30, 'drink' => 30,
            'drinking_habits' => 30, 'body_type' => 30, 'complexion' => 30, 'pet' => 30,
            'languages_known' => 500, 'hobbies' => 1000, 'interests' => 1000,
        ] as $field => $max) {
            if (array_key_exists($field, $in)) {
                $data[$field] = Validation::text((string) $in[$field], $max) ?: null;
            }
        }

        // ---- horoscope ----
        foreach (['rashi' => 40, 'nakshatra' => 40, 'place_of_birth' => 120] as $field => $max) {
            if (array_key_exists($field, $in)) {
                $data[$field] = Validation::text((string) $in[$field], $max) ?: null;
            }
        }
        if (array_key_exists('time_of_birth', $in)) {
            $t = trim((string) $in['time_of_birth']);
            if ($t === '') {
                $data['time_of_birth'] = null;
            } elseif (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $t)) {
                $errors['time_of_birth'] = 'Enter the time as HH:MM.';
            } else {
                $data['time_of_birth'] = $t;
            }
        }

        // ---- location extras (profile_lifestyle) ----
        if (array_key_exists('residency_status', $in)) {
            $data['residency_status'] = Validation::text((string) $in['residency_status'], 40) ?: null;
        }
        if (array_key_exists('willing_to_relocate', $in)) {
            $data['willing_to_relocate'] = !empty($in['willing_to_relocate']) ? 1 : 0;
        }
        foreach (['latitude' => [-90, 90, 'Latitude'], 'longitude' => [-180, 180, 'Longitude']] as $field => [$min, $max, $label]) {
            if (!array_key_exists($field, $in)) {
                continue;
            }
            $raw = trim((string) $in[$field]);
            if ($raw === '') {
                $data[$field] = null;
                continue;
            }
            if (!is_numeric($raw) || (float) $raw < $min || (float) $raw > $max) {
                $errors[$field] = $label . ' must be between ' . $min . ' and ' . $max . '.';
            } else {
                $data[$field] = (float) $raw;
            }
        }

        // ---- partner preferences ----
        foreach ([
            'min_age' => [18, 100, 'Minimum age'], 'max_age' => [18, 100, 'Maximum age'],
            'min_height_cm' => [50, 280, 'Minimum height'], 'max_height_cm' => [50, 280, 'Maximum height'],
        ] as $field => [$min, $max, $label]) {
            if (!array_key_exists($field, $in)) {
                continue;
            }
            $raw = trim((string) $in[$field]);
            if ($raw === '') {
                $data[$field] = null;
                continue;
            }
            $v = Validation::intRange($raw, $min, $max);
            if ($v === null) {
                $errors[$field] = $label . ' must be between ' . $min . ' and ' . $max . '.';
            } else {
                $data[$field] = $v;
            }
        }

        // Cross-field validation: age range
        if (isset($data['min_age']) && isset($data['max_age'])) {
            if ($data['min_age'] > $data['max_age']) {
                $errors['max_age'] = 'Maximum age must be greater than or equal to minimum age.';
            }
        }

        // Cross-field validation: height range
        if (isset($data['min_height_cm']) && isset($data['max_height_cm'])) {
            if ($data['min_height_cm'] > $data['max_height_cm']) {
                $errors['max_height_cm'] = 'Maximum height must be greater than or equal to minimum height.';
            }
        }
        if (array_key_exists('pref_income_min', $in)) {
            $data['pref_income_min'] = Validation::text((string) $in['pref_income_min'], 30) ?: null;
        }
        foreach (self::PREFERENCE_JSON_FIELDS as $field) {
            if (array_key_exists($field, $in)) {
                $data[$field] = self::encodePrefList($in[$field]);
            }
        }

        // ---- privacy ----
        foreach ([
            'profile_visibility', 'show_phone', 'show_email', 'show_photos',
            'show_online_status', 'receive_interests', 'allow_contact_reveal',
        ] as $field) {
            if (array_key_exists($field, $in)) {
                $data[$field] = !empty($in[$field]) ? 1 : 0;
            }
        }
        if (array_key_exists('privacy_preset', $in)) {
            $data['privacy_preset'] = Validation::enum($in['privacy_preset'] ?? null, ['public', 'members', 'private'], 'members');
        }

        /*
         * Track which controls were actually submitted. update() must only
         * touch those columns — a partial payload would otherwise NULL every
         * field it happened to omit and silently destroy profile data.
         */
        $provided = [];
        foreach (array_keys($data) as $k) {
            if (array_key_exists($k, $in)) {
                $provided[] = $k;
            }
        }
        // `phone_normalized` and `email_normalized` are derived, never submitted.
        if (in_array('phone', $provided, true)) {
            $provided[] = 'phone_normalized';
        }
        if (in_array('email', $provided, true)) {
            $provided[] = 'email_normalized';
        }

        return [
            'data' => $data,
            'errors' => $errors,
            'password' => $password,
            'provided' => array_values(array_unique($provided)),
        ];
    }

    /** @return array<string,string> */
    private static function validatePassword(string $password): array
    {
        $errors = [];
        if (mb_strlen($password) < self::PASSWORD_MIN) {
            $errors['password'] = sprintf('Password must be at least %d characters.', self::PASSWORD_MIN);
        } elseif (!preg_match('/[a-z]/', $password)) {
            $errors['password'] = 'Password must contain at least one lowercase letter.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors['password'] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Password must contain at least one number.';
        }
        return $errors;
    }

    private static function ageOn(\DateTimeImmutable $dob, \DateTimeImmutable $on): int
    {
        $age = $dob->diff($on)->y;
        if ($dob->format('m-d') > $on->format('m-d')) {
            $age--;
        }
        return $age;
    }

    private static function rawField(int $userId, string $column): ?string
    {
        if (!in_array($column, ['email', 'role'], true)) {
            return null;
        }
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare("SELECT {$column} FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (string) $v;
    }

    // -------------------------------------------------------------- create

    /**
     * Create an account together with its profile.
     *
     * @return array{success:bool,id?:int,error?:string}
     */
    public static function create(int $adminId, bool $isSuper, array $input, array $files = []): array
    {
        $v = self::validate($input, true, $adminId, $isSuper);
        if ($v['errors']) {
            return ['success' => false, 'error' => self::firstError($v['errors']), 'errors' => $v['errors']];
        }
        $d = $v['data'];

        // Reject a bad photo before any row is written.
        $photo = self::photoFile($files);
        if (isset($photo['error'])) {
            return ['success' => false, 'error' => $photo['error'], 'errors' => ['photo' => $photo['error']]];
        }
        // Same for the horoscope chart.
        $kattam = self::kattamFile($files);
        if (isset($kattam['error'])) {
            return ['success' => false, 'error' => $kattam['error'], 'errors' => ['kattam' => $kattam['error']]];
        }

        $pdo = Connection::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO users
                   (email, email_normalized, phone, phone_normalized, password_hash,
                    role, account_status, is_active, is_verified, email_verified_at)
                 VALUES (:e, :en, :ph, :phn, :pw, :role, :st, :act, :ver, :ev)'
            )->execute([                ':e' => $d['email'],
                ':en' => $d['email'],
                ':ph' => $d['phone'] ?? null,
                ':phn' => $d['phone_normalized'] ?? null,
                ':pw' => password_hash($v['password'], PASSWORD_DEFAULT),
                ':role' => $d['role'],
                ':st' => $d['account_status'],
                ':act' => $d['is_active'],
                ':ver' => !empty($d['is_verified']) ? 1 : 0,
                ':ev' => !empty($d['is_verified']) ? date('Y-m-d H:i:s') : null,
            ]);
            $userId = (int) $pdo->lastInsertId();

            self::upsertProfile($pdo, $userId, $d);
            self::upsertRelated($pdo, $userId, $d);
            // Guarantee every admin-created account is visible/opt-in by default,
            // so its profile actually shows up in browse/matches even when the
            // admin form did not carry privacy checkboxes.
            $pdo->prepare("INSERT INTO privacy_settings (user_id, profile_visibility, show_phone, show_email, show_photos, show_online_status, receive_interests, allow_contact_reveal) VALUES (:u, 1, 0, 0, 1, 1, 1, 1) ON DUPLICATE KEY UPDATE user_id = user_id")
                ->execute([':u' => $userId]);
            $pdo->prepare("UPDATE profiles SET moderation_status = 'approved', is_hidden = 0 WHERE user_id = :u AND (moderation_status IS NULL OR moderation_status = 'pending')")
                ->execute([':u' => $userId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            AuditService::log($adminId, 'admin.user.create', 'user', null, ['email' => $d['email']], 'failure');
            $msg = str_contains($e->getMessage(), 'Duplicate')
                ? 'An account with that email already exists.'
                : 'Could not create the account.';
            return ['success' => false, 'error' => $msg, 'errors' => ['email' => $msg]];
        }

        AuditService::log($adminId, 'admin.user.create', 'user', $userId, [
            'email' => $d['email'],
            'role' => $d['role'],
            'status' => $d['account_status'],
        ]);

        // The photo is stored after the account row exists (addPhoto runs its
        // own transaction, so it cannot nest inside the one above).
        $warning = null;
        if (isset($photo['file']) && (new ProfileService())->addPhoto($userId, $photo['file']) === null) {
            $warning = 'The account was created, but the profile photo could not be saved.';
        }
        if (isset($kattam['file']) && !self::saveKattam($userId, $kattam['file'])) {
            $warning = trim(($warning !== null ? $warning . ' ' : '') . 'The horoscope chart could not be saved.');
        }

        try {
            NotificationService::notify(
                $userId,
                'account_created',
                'Your account is ready',
                'An administrator created a Matrimony account for you. Sign in with the email address you were given.',
                'user',
                $userId
            );
            EmailOutboxService::queue($userId, 'account_created', [
                'message' => 'An administrator created your account.',
            ]);
        } catch (\Throwable $e) {
            // Notifications are best-effort; never fail the creation over them.
        }

        return $warning === null
            ? ['success' => true, 'id' => $userId]
            : ['success' => true, 'id' => $userId, 'warning' => $warning];
    }

    // -------------------------------------------------------------- update

    /**
     * Update account + profile fields on an existing user.
     *
     * @return array{success:bool,id?:int,error?:string}
     */
    public static function update(int $adminId, bool $isSuper, int $userId, array $input, array $files = []): array
    {
        $existing = self::find($userId);
        if (!$existing) {
            return ['success' => false, 'error' => 'User not found.', 'errors' => ['email' => 'User not found.']];
        }
        if (!$isSuper && in_array((string) $existing['role'], self::PRIVILEGED_ROLES, true)) {
            return [
                'success' => false,
                'error' => 'Only a Super Admin can modify a privileged account.',
                'errors' => ['role' => 'Only a Super Admin can modify a privileged account.'],
            ];
        }
        if (array_key_exists('is_verified', $input) && !$isSuper) {
            return [
                'success' => false,
                'error' => 'Only a Super Admin can change verification state.',
                'errors' => ['is_verified' => 'Only a Super Admin can change verification state.'],
            ];
        }
        if ($userId === $adminId && in_array((string) $existing['role'], self::PRIVILEGED_ROLES, true)) {
            $nextRole = (string) ($input['role'] ?? $existing['role']);
            if ($nextRole !== (string) $existing['role']) {
                return [
                    'success' => false,
                    'error' => 'You cannot change your own role.',
                    'errors' => ['role' => 'You cannot change your own role.'],
                ];
            }
        }

        $v = self::validate($input, false, $adminId, $isSuper, $userId);
        if ($v['errors']) {
            return ['success' => false, 'error' => self::firstError($v['errors']), 'errors' => $v['errors']];
        }
        $d = $v['data'];

        // Reject a bad photo before any row is written.
        $photo = self::photoFile($files);
        if (isset($photo['error'])) {
            return ['success' => false, 'error' => $photo['error'], 'errors' => ['photo' => $photo['error']]];
        }
        // Same for the horoscope chart.
        $kattam = self::kattamFile($files);
        if (isset($kattam['error'])) {
            return ['success' => false, 'error' => $kattam['error'], 'errors' => ['kattam' => $kattam['error']]];
        }
        $removeKattam = !empty($input['remove_kattam']);

        // Only write the columns this request actually carried.
        $provided = $v['provided'];
        $d = array_intersect_key($d, array_flip($provided));
        if (!$d && !isset($photo['file']) && !isset($kattam['file']) && !$removeKattam) {
            return ['success' => false, 'error' => 'Nothing to update.', 'errors' => ['email' => 'Nothing to update.']];
        }

        // Split across the two tables — profile columns must never reach `users`.
        $accountSet = array_intersect_key($d, array_flip(self::ACCOUNT_FIELDS));
        $profileSet = array_intersect_key($d, array_flip(self::PROFILE_FIELDS));

        // `email_normalized` is what sign-in looks up; keep it in step.
        if (isset($accountSet['email'])) {
            $accountSet['email_normalized'] = strtolower((string) $accountSet['email']);
        }

        $pdo = Connection::pdo();
        $pdo->beginTransaction();
        try {
            if ($accountSet) {
                $sets = [];
                $params = [];
                foreach ($accountSet as $col => $val) {
                    $sets[] = "{$col} = :{$col}";
                    $params[':' . $col] = $val;
                }
                // Derive is_active whenever the status was part of the submission.
                if (isset($accountSet['account_status']) && !isset($accountSet['is_active'])) {
                    $sets[] = 'is_active = :is_active';
                    $params[':is_active'] = in_array($accountSet['account_status'], ['active', 'pending_verification'], true) ? 1 : 0;
                }
                $params[':id'] = $userId;
                $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
            }

            if (array_key_exists('is_verified', $accountSet)) {
                if (!empty($accountSet['is_verified'])) {
                    $pdo->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = :id AND email_verified_at IS NULL')
                        ->execute([':id' => $userId]);
                } else {
                    $pdo->prepare('UPDATE users SET email_verified_at = NULL WHERE id = :id')->execute([':id' => $userId]);
                }
            }

            if ($profileSet) {
                self::upsertProfile($pdo, $userId, $profileSet, array_keys($profileSet));
            }
            self::upsertRelated($pdo, $userId, $d);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            AuditService::log($adminId, 'admin.user.update', 'user', $userId, [
                'error' => get_class($e) . ': ' . $e->getMessage(),
                'fields' => array_keys($d),
            ], 'failure');
            if ($e instanceof \RuntimeException) {
                // A deliberate, human-readable guard — surface it as-is.
                $msg = $e->getMessage();
                return ['success' => false, 'error' => $msg, 'errors' => ['first_name' => $msg]];
            }
            $msg = str_contains($e->getMessage(), 'Duplicate')
                ? 'Another account already uses that email address.'
                : 'Could not save the account.';
            return ['success' => false, 'error' => $msg, 'errors' => ['email' => $msg]];
        }

        $changes = self::diff($existing, $d);
        AuditService::log($adminId, 'admin.user.update', 'user', $userId, $changes);

        // A status change away from active must invalidate live sessions.
        if (isset($accountSet['account_status']) && $accountSet['account_status'] !== 'active') {
            Auth::revokeUserSessions($userId);
        }

        $warning = null;
        if (isset($photo['file']) && (new ProfileService())->addPhoto($userId, $photo['file']) === null) {
            $warning = 'Saved, but the profile photo could not be uploaded.';
        }
        if (isset($kattam['file'])) {
            if (self::saveKattam($userId, $kattam['file'])) {
                $changes['kattam'] = ['from' => '', 'to' => 'chart replaced'];
            } else {
                $warning = trim(($warning !== null ? $warning . ' ' : '') . 'Saved, but the horoscope chart could not be stored.');
            }
        } elseif ($removeKattam) {
            self::clearKattam($userId);
            $changes['kattam'] = ['from' => 'chart', 'to' => ''];
        }

        try {
            NotificationService::notify(
                $userId,
                'account_updated',
                'Your account was updated',
                'An administrator updated your Matrimony account details.',
                'user',
                $userId
            );
        } catch (\Throwable $e) {
            // best-effort
        }

        $result = ['success' => true, 'id' => $userId, 'changed' => array_keys($changes)];
        if ($warning !== null) {
            $result['warning'] = $warning;
        }
        return $result;
    }

    // -------------------------------------------------------------- delete

    /** Soft delete: flag the account and revoke every session. */
    public static function delete(int $adminId, int $userId, string $reason): array
    {
        if ($adminId === $userId) {
            return ['success' => false, 'error' => 'You cannot delete your own account.'];
        }
        $target = self::find($userId);
        if (!$target) {
            return ['success' => false, 'error' => 'User not found.'];
        }
        if (trim($reason) === '') {
            return ['success' => false, 'error' => 'A reason is required to delete an account.'];
        }
        if (in_array((string) $target['role'], self::PRIVILEGED_ROLES, true)) {
            if (!Authorization::isSuperAdmin()) {
                return ['success' => false, 'error' => 'Only a Super Admin can delete a privileged account.'];
            }
            if ((string) $target['role'] === 'super_admin') {
                // Atomic guard against TOCTOU on concurrent last-super-admin deletion.
                $pdo = Connection::pdo();
                $guard = $pdo->prepare(
                    "UPDATE users SET account_status = 'deleted' WHERE id = :id AND (
                        SELECT cnt FROM (SELECT COUNT(*) cnt FROM users WHERE role='super_admin' AND account_status='active' AND id <> :excl) AS c
                    ) >= 1"
                );
                // If guard cannot be satisfied (sole super), abort before touching anything.
                $still = self::activeSuperAdminCount();
                if ($still <= 1) {
                    return ['success' => false, 'error' => 'Cannot delete the last active Super Admin.'];
                }
            }
        }
        if ((string) $target['account_status'] === 'deleted') {
            return ['success' => false, 'error' => 'That account is already deleted.'];
        }

        $pdo = Connection::pdo();
        try {
            $pdo->beginTransaction();
            // Re-check inside the transaction (row updated under lock-less DB, but at
            // least prevents logically deleting twice within one TX).
            $chk = $pdo->prepare('SELECT account_status FROM users WHERE id = :id FOR UPDATE');
            try {
                $chk->execute([':id' => $userId]);
            } catch (\Throwable $e) {
                // FOR UPDATE unsupported — fall back to a plain read.
                $chk = $pdo->prepare('SELECT account_status FROM users WHERE id = :id');
                $chk->execute([':id' => $userId]);
            }
            $curStatus = $chk->fetchColumn();
            if ($curStatus === false || $curStatus === 'deleted') {
                throw new \RuntimeException('That account is already deleted.');
            }
            $pdo->prepare("UPDATE users SET account_status = 'deleted', is_active = 0 WHERE id = :id")
                ->execute([':id' => $userId]);
            try {
                $pdo->prepare("UPDATE profiles SET is_hidden = 1, moderation_status = 'hidden' WHERE user_id = :id")
                    ->execute([':id' => $userId]);
            } catch (\Throwable $e) {
            }
            try {
                $pdo->prepare("UPDATE memberships SET status = 'suspended' WHERE user_id = :id AND status = 'active'")
                    ->execute([':id' => $userId]);
            } catch (\Throwable $e) {
            }
            try {
                $pdo->prepare('DELETE FROM contact_reveals WHERE user_id = :id OR target_user_id = :id2')
                    ->execute([':id' => $userId, ':id2' => $userId]);
            } catch (\Throwable $e) {
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            @error_log('AdminUserService::delete failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Could not delete the account. Please try again.'];
        }
        Auth::revokeUserSessions($userId);
        AuditService::log($adminId, 'admin.user.delete', 'user', $userId, [
            'reason' => $reason,
            'from_status' => $target['account_status'],
            'email' => $target['email'],
        ]);
        return ['success' => true];
    }

    /** Undo a soft delete. */
    public static function restore(int $adminId, int $userId): array
    {
        $target = self::find($userId);
        if (!$target) {
            return ['success' => false, 'error' => 'User not found.'];
        }
        if ((string) $target['account_status'] !== 'deleted') {
            return ['success' => false, 'error' => 'That account is not deleted.'];
        }
        if (in_array((string) $target['role'], self::PRIVILEGED_ROLES, true) && !Authorization::isSuperAdmin()) {
            return ['success' => false, 'error' => 'Only a Super Admin can restore a privileged account.'];
        }

        $pdo = Connection::pdo();
        try {
            $pdo->beginTransaction();
            $chk = $pdo->prepare('SELECT account_status FROM users WHERE id = :id');
            $chk->execute([':id' => $userId]);
            if ((string) $chk->fetchColumn() !== 'deleted') {
                throw new \RuntimeException('That account is not deleted.');
            }
            $pdo->prepare("UPDATE users SET account_status = 'active', is_active = 1 WHERE id = :id")
                ->execute([':id' => $userId]);
            // Bring the public profile back into play.
            try {
                $pdo->prepare("UPDATE profiles SET is_hidden = 0, moderation_status = 'approved' WHERE user_id = :id")
                    ->execute([':id' => $userId]);
            } catch (\Throwable $e) {
            }
            // Reactivate memberships that were suspended by the deletion.
            try {
                $pdo->prepare("UPDATE memberships SET status = 'active' WHERE user_id = :id AND status = 'suspended'")
                    ->execute([':id' => $userId]);
            } catch (\Throwable $e) {
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            @error_log('AdminUserService::restore failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Could not restore the account. Please try again.'];
        }
        AuditService::log($adminId, 'admin.user.restore', 'user', $userId, ['email' => $target['email']]);
        return ['success' => true];
    }

    // ------------------------------------------------------------ password

    /**
     * Set a user's password directly. Also used for "forgot password" resets
     * performed by an operator.
     */
    public static function setPassword(int $adminId, int $userId, string $password, bool $mustChange = true): array
    {
        if ($adminId === $userId) {
            return ['success' => false, 'error' => 'Use your own password change screen to change your own password.'];
        }
        $target = self::find($userId);
        if (!$target) {
            return ['success' => false, 'error' => 'User not found.'];
        }
        $errors = self::validatePassword($password);
        if ($errors) {
            return ['success' => false, 'error' => reset($errors), 'errors' => $errors];
        }

        $pdo = Connection::pdo();
        $pdo->prepare(
            'UPDATE users SET password_hash = :pw, must_reauth_at = :mr WHERE id = :id'
        )->execute([
            ':pw' => password_hash($password, PASSWORD_DEFAULT),
            ':mr' => $mustChange ? date('Y-m-d H:i:s', time() + 86400) : null,
            ':id' => $userId,
        ]);

        Auth::revokeUserSessions($userId);
        AuditService::log($adminId, 'admin.user.password_reset', 'user', $userId, [
            'must_change' => $mustChange,
            'email' => $target['email'],
        ]);

        return ['success' => true];
    }

    // ------------------------------------------------------------- helpers

    /**
     * Insert or update the profile row for a user.
     * `profiles.user_id` is unique, so this must run inside the same
     * transaction as the users write.
     *
     * @param string[]|null $fields columns to write; null means "all of them"
     */
    private static function upsertProfile(\PDO $pdo, int $userId, array $d, ?array $fields = null): void
    {
        $all = self::PROFILE_FIELDS;
        $fields = $fields === null ? $all : array_values(array_intersect($all, $fields));
        if (!$fields) {
            return;
        }

        $exists = $pdo->prepare('SELECT id FROM profiles WHERE user_id = :u LIMIT 1');
        $exists->execute([':u' => $userId]);

        if ($exists->fetchColumn()) {
            $sets = implode(', ', array_map(static fn($f) => "{$f} = :{$f}", $fields));
            $params = [':user_id' => $userId];
            foreach ($fields as $f) {
                $params[':' . $f] = $d[$f] ?? null;
            }
            $pdo->prepare("UPDATE profiles SET {$sets} WHERE user_id = :user_id")->execute($params);
            return;
        }

        $cols = implode(', ', array_merge(['user_id'], $fields));
        $place = ':user_id, :' . implode(', :', $fields);
        $params = [':user_id' => $userId];
        foreach ($fields as $f) {
            $params[':' . $f] = $d[$f] ?? null;
        }

        /*
         * A partial save must not try to INSERT: first_name / last_name /
         * gender / date_of_birth are NOT NULL with no default, so MySQL strict
         * mode rejects a half-filled row. Refuse with something actionable.
         */
        $missing = array_diff(['first_name', 'last_name', 'gender', 'date_of_birth'], $fields);
        if ($missing) {
            throw new \RuntimeException(
                'This account has no profile yet. Open the full edit form once to create it '
                . '(missing: ' . implode(', ', $missing) . ').'
            );
        }

        $pdo->prepare("INSERT INTO profiles ({$cols}) VALUES ({$place})")->execute($params);
    }

    /**
     * Upsert every related profile table (family, habits, horoscope, lifestyle,
     * preferences, privacy). Only columns actually present in $d are touched, so
     * a partial save never wipes the rest of the row.
     *
     * @param string[]|null $fields extra allow-list; null means "any column in $d"
     */
    private static function upsertRelated(\PDO $pdo, int $userId, array $d, ?array $fields = null): void
    {
        foreach (self::RELATED_TABLES as $table => $cols) {
            $write = [];
            foreach ($cols as $col) {
                if (($fields === null || in_array($col, $fields, true)) && array_key_exists($col, $d)) {
                    $write[] = $col;
                }
            }
            if ($write) {
                self::upsertTable($pdo, $table, $userId, $d, $write);
            }
        }
    }

    /**
     * Insert or update one user-scoped related row. $table comes from the
     * RELATED_TABLES constant map, never from request input.
     *
     * @param string[] $fields
     */
    private static function upsertTable(\PDO $pdo, string $table, int $userId, array $d, array $fields): void
    {
        $exists = $pdo->prepare("SELECT 1 FROM {$table} WHERE user_id = :u LIMIT 1");
        $exists->execute([':u' => $userId]);

        $params = [':user_id' => $userId];
        foreach ($fields as $f) {
            $params[':' . $f] = $d[$f] ?? null;
        }

        if ($exists->fetchColumn()) {
            $sets = implode(', ', array_map(static fn($f) => "{$f} = :{$f}", $fields));
            $pdo->prepare("UPDATE {$table} SET {$sets} WHERE user_id = :user_id")->execute($params);
            return;
        }

        $cols = implode(', ', array_merge(['user_id'], $fields));
        $place = ':user_id, :' . implode(', :', $fields);
        $pdo->prepare("INSERT INTO {$table} ({$cols}) VALUES ({$place})")->execute($params);
    }

    /**
     * Inspect an uploaded photo from $_FILES.
     *
     * @return array{file?:array,error?:string} empty when no file was sent
     */
    private static function photoFile(array $files): array
    {
        $file = $files['photo'] ?? null;
        if (!is_array($file) || !array_key_exists('error', $file)) {
            return [];
        }
        $err = (int) $file['error'];
        if ($err === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['error' => 'The photo upload failed (code ' . $err . ').'];
        }
        if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return ['error' => 'The photo must be 5 MB or smaller.'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['error' => 'The photo upload could not be verified.'];
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return ['error' => 'Upload a JPG, PNG, WebP or GIF image.'];
        }
        return ['file' => $file];
    }

    /**
     * Inspect an uploaded horoscope chart ("kattam") from $_FILES.
     *
     * @return array{file?:array,error?:string} empty when no file was sent
     */
    private static function kattamFile(array $files): array
    {
        $file = $files['kattam'] ?? null;
        if (!is_array($file) || !array_key_exists('error', $file)) {
            return [];
        }
        $err = (int) $file['error'];
        if ($err === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['error' => 'The chart upload failed (code ' . $err . ').'];
        }
        if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return ['error' => 'The horoscope chart must be 5 MB or smaller.'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['error' => 'The chart upload could not be verified.'];
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return ['error' => 'Upload the chart as a JPG, PNG, WebP or GIF image.'];
        }
        return ['file' => $file];
    }

    /** Validate and store an uploaded chart, replacing any existing one. */
    private static function saveKattam(int $userId, array $file): bool
    {
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return false;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return false;
        }
        if (!@getimagesize($tmp)) {
            return false;
        }
        $data = file_get_contents($tmp);
        if ($data === false) {
            return false;
        }
        self::storeKattam($userId, $data, $mime);
        return true;
    }

    /** Write the chart binary, creating the horoscope row when needed. */
    private static function storeKattam(int $userId, string $data, string $mime): void
    {
        $pdo = Connection::pdo();
        $exists = $pdo->prepare('SELECT 1 FROM profile_horoscope WHERE user_id = :u LIMIT 1');
        $exists->execute([':u' => $userId]);
        if ($exists->fetchColumn()) {
            $pdo->prepare('UPDATE profile_horoscope SET kattam_data = :d, kattam_mime = :m, kattam_updated_at = NOW() WHERE user_id = :u')
                ->execute([':d' => $data, ':m' => $mime, ':u' => $userId]);
            return;
        }
        $pdo->prepare('INSERT INTO profile_horoscope (user_id, kattam_data, kattam_mime, kattam_updated_at) VALUES (:u, :d, :m, NOW())')
            ->execute([':u' => $userId, ':d' => $data, ':m' => $mime]);
    }

    /** Remove a stored chart, if any. */
    private static function clearKattam(int $userId): void
    {
        Connection::pdo()
            ->prepare('UPDATE profile_horoscope SET kattam_data = NULL, kattam_mime = NULL, kattam_updated_at = NULL WHERE user_id = :u')
            ->execute([':u' => $userId]);
    }

    /**
     * Form input -> JSON array string (or 'null' when empty).
     *
     * Accepts both a multi-select array (`pref_religion[]`) and a plain
     * comma-separated string, so a free-text field and a dropdown can feed
     * the same stored column.
     */
    private static function encodePrefList(mixed $raw): string
    {
        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);
        $items = [];
        foreach ($parts as $part) {
            $part = mb_substr(trim((string) $part), 0, 60);
            if ($part !== '') {
                $items[] = $part;
            }
        }
        return $items === [] ? 'null' : (string) json_encode($items, JSON_UNESCAPED_UNICODE);
    }

    /** Stored JSON (array|string|null) -> comma-separated text for the form. */
    private static function decodePrefList(mixed $raw): string
    {
        if ($raw === null || $raw === '' || $raw === 'null') {
            return '';
        }
        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded)) {
            return implode(', ', array_map(static fn($v): string => (string) $v, $decoded));
        }
        return is_string($decoded) ? $decoded : (string) $raw;
    }

    /** Only report fields that actually changed, for the audit trail. */
    private static function diff(array $before, array $after): array
    {
        $out = [];
        foreach ($after as $k => $v) {
            if (in_array($k, self::PREFERENCE_JSON_FIELDS, true)) {
                $v = self::decodePrefList($v);
            }
            $old = $before[$k] ?? null;
            $oldS = $old === null ? '' : (string) $old;
            $newS = $v === null ? '' : (string) $v;
            if ($oldS !== $newS) {
                $out[$k] = ['from' => $oldS, 'to' => $newS];
            }
        }
        return $out;
    }

    private static function firstError(array $errors): string
    {
        return (string) reset($errors);
    }

    private static function activeSuperAdminCount(): int
    {
        $pdo = Connection::pdo();
        return (int) $pdo->query(
            "SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND account_status = 'active'"
        )->fetchColumn();
    }

    // ------------------------------------------------- status / role (legacy)

    public static function setStatus(int $adminId, bool $isSuper, int $userId, string $status, string $reason): array
    {
        if (trim($reason) === '') {
            return ['success' => false, 'error' => 'Reason is required'];
        }
        if (!in_array($status, self::SETTABLE_STATUSES, true)) {
            return ['success' => false, 'error' => 'Invalid status'];
        }
        $pdo = Connection::pdo();
        $s = $pdo->prepare('SELECT id, role, account_status FROM users WHERE id = :id LIMIT 1');
        $s->execute([':id' => $userId]);
        $t = $s->fetch();
        if (!$t) {
            return ['success' => false, 'error' => 'User not found'];
        }
        if (in_array($t['role'], self::PRIVILEGED_ROLES, true) && !$isSuper) {
            return ['success' => false, 'error' => 'Only Super Admin can change privileged accounts'];
        }
        if ($adminId === $userId) {
            return ['success' => false, 'error' => 'You cannot change your own account status'];
        }
        $dbStatus = $status === 'active' ? 'active' : $status;
        $pdo->prepare('UPDATE users SET account_status = :s, is_active = :a WHERE id = :id')->execute([
            ':s' => $dbStatus,
            ':a' => $status === 'active' ? 1 : 0,
            ':id' => $userId,
        ]);
        if ($status !== 'active') {
            Auth::revokeUserSessions($userId);
        }
        AuditService::log($adminId, 'admin.user.status', 'user', $userId, [
            'from' => $t['account_status'],
            'to' => $dbStatus,
            'reason' => $reason,
        ]);
        NotificationService::notify(
            $userId,
            'account_status',
            'Account ' . $status,
            'Your account was ' . $status . '. Reason: ' . $reason
        );
        return ['success' => true];
    }

    public static function setRole(int $adminId, int $userId, string $role): array
    {
        if (!in_array($role, ['user', 'moderator', 'admin', 'super_admin'], true)) {
            return ['success' => false, 'error' => 'Invalid role'];
        }
        $pdo = Connection::pdo();
        $s = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
        $s->execute([':id' => $userId]);
        $cur = $s->fetchColumn();
        if ($cur === false) {
            return ['success' => false, 'error' => 'User not found'];
        }
        // Last-super-admin guard (statement-atomic via subquery).
        if (is_string($cur) && $cur === 'super_admin' && $role !== 'super_admin') {
            try {
                $cnt = (int) $pdo->query(
                    "SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND account_status = 'active' AND id <> {$userId}"
                )->fetchColumn();
                if ($cnt < 0) {
                    // already 0 active others => abort
                }
                if ($cnt === 0) {
                    return ['success' => false, 'error' => 'Cannot demote the last active Super Admin'];
                }
            } catch (\Throwable $e) {
                return ['success' => false, 'error' => 'Cannot demote the last active Super Admin'];
            }
        }
        if ($adminId === $userId && $cur !== $role) {
            return ['success' => false, 'error' => 'You cannot change your own role'];
        }
        try {
            $pdo->beginTransaction();
            $exec = $pdo->prepare('UPDATE users SET role = :r WHERE id = :id');
            $exec->execute([':r' => $role, ':id' => $userId]);
            if ($exec->rowCount() === 0) {
                throw new \RuntimeException('User not found');
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'error' => 'Role change failed.'];
        }
        if ($cur !== $role) {
            Auth::revokeUserSessions($userId);
        }
        AuditService::log($adminId, 'admin.user.role', 'user', $userId, ['from' => $cur, 'to' => $role]);
        return ['success' => true];
    }
}
