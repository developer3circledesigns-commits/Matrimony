-- ============================================================================
-- Matrimony Complete Database Schema
-- Single comprehensive SQL file for the entire project
-- ============================================================================
-- Run on a fresh database to bootstrap the matrimony platform.
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ============================================================================
-- CORE TABLES
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Users (account, login)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    is_verified   TINYINT(1)   NOT NULL DEFAULT 0,
    last_login_at DATETIME     NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Profiles (matrimonial details)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS profiles (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    first_name      VARCHAR(80)  NOT NULL,
    last_name       VARCHAR(80)  NOT NULL,
    gender          ENUM('male','female','other') NOT NULL,
    date_of_birth   DATE         NOT NULL,
    marital_status  ENUM('never_married','divorced','widowed','awaiting_divorce') NOT NULL DEFAULT 'never_married',
    religion        VARCHAR(60)  NULL,
    caste           VARCHAR(80)  NULL,
    mother_tongue   VARCHAR(60)  NULL,
    has_children    ENUM('yes','no') NULL,
    height_cm       SMALLINT     NULL,
    weight_kg       SMALLINT     NULL,
    education       VARCHAR(120) NULL,
    institution     VARCHAR(120) NULL,
    occupation      VARCHAR(120) NULL,
    company         VARCHAR(120) NULL,
    annual_income   VARCHAR(60)  NULL,
    city            VARCHAR(80)  NULL,
    work_location   VARCHAR(120) NULL,
    state           VARCHAR(80)  NULL,
    country         VARCHAR(80)  NULL,
    created_by      ENUM('self','parent','guardian','sibling','friend') NOT NULL DEFAULT 'self',
    created_by_name VARCHAR(80)  NULL,
    sub_caste       VARCHAR(80)  NULL,
    about_me        TEXT         NULL,
    partner_prefs   TEXT         NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_profiles_user (user_id),
    CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Profile photos
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS profile_photos (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id       BIGINT UNSIGNED NOT NULL,
    path          VARCHAR(255) NOT NULL,
    is_primary    TINYINT(1)   NOT NULL DEFAULT 0,
    privacy_level ENUM('public','protected','private') NOT NULL DEFAULT 'public',
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
    caption       VARCHAR(255) NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_photos_user (user_id),
    KEY idx_photos_user_primary (user_id, is_primary),
    CONSTRAINT fk_photos_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Memberships / subscription plans
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS membership_plans (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(40)  NOT NULL,
    name          VARCHAR(80)  NOT NULL,
    duration_days INT UNSIGNED NOT NULL,
    price_cents   INT UNSIGNED NOT NULL,
    features      TEXT         NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_plans_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS memberships (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL,
    plan_id    INT UNSIGNED    NOT NULL,
    starts_at  DATETIME  NOT NULL,
    ends_at    DATETIME  NOT NULL,
    status     ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
    created_at DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_memberships_user (user_id),
    CONSTRAINT fk_memberships_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_memberships_plan FOREIGN KEY (plan_id) REFERENCES membership_plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Matches — mutual interest / "shortlist" / "interested"
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS matches (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id      BIGINT UNSIGNED NOT NULL,
    target_id    BIGINT UNSIGNED NOT NULL,
    status       ENUM('interested','shortlisted','declined','mutual','blocked') NOT NULL DEFAULT 'interested',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_matches (user_id, target_id),
    KEY idx_matches_user_status (user_id, status),
    KEY idx_matches_target (target_id, status),
    CONSTRAINT fk_matches_user   FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_matches_target FOREIGN KEY (target_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Messages
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sender_id   BIGINT UNSIGNED NOT NULL,
    receiver_id BIGINT UNSIGNED NOT NULL,
    body        TEXT NOT NULL,
    read_at     DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_messages_sender   (sender_id),
    KEY idx_messages_receiver (receiver_id, created_at),
    CONSTRAINT fk_messages_sender   FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- EXTENDED PROFILE TABLES
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Profile assets / lifestyle preferences
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS profile_assets (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id          BIGINT UNSIGNED NOT NULL,
    diet             VARCHAR(30) NULL,
    smoke            VARCHAR(30) NULL,
    smoking_habits   VARCHAR(30) NULL,
    drink            VARCHAR(30) NULL,
    drinking_habits  VARCHAR(30) NULL,
    body_type        VARCHAR(30) NULL,
    complexion       VARCHAR(30) NULL,
    languages_known  TEXT NULL,
    hobbies          TEXT NULL,
    interests        TEXT NULL,
    pet              VARCHAR(30) NULL,
    UNIQUE KEY uq_assets_user (user_id),
    CONSTRAINT fk_assets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Profile family
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS profile_family (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id           BIGINT UNSIGNED NOT NULL,
    father_name       VARCHAR(100) NULL,
    father_occupation VARCHAR(120) NULL,
    mother_name       VARCHAR(100) NULL,
    mother_occupation VARCHAR(120) NULL,
    brothers_count    INT UNSIGNED NULL,
    sisters_count     INT UNSIGNED NULL,
    siblings          VARCHAR(30) NULL,
    family_type       ENUM('joint','nuclear') NULL,
    family_values     ENUM('traditional','moderate','liberal') NULL,
    family_income     VARCHAR(50) NULL,
    family_origin     VARCHAR(100) NULL,
    about_family      TEXT NULL,
    UNIQUE KEY uq_family_user (user_id),
    CONSTRAINT fk_family_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Profile horoscope / astrology
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS profile_horoscope (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id        BIGINT UNSIGNED NOT NULL,
    rashi          VARCHAR(40) NULL,
    nakshatra      VARCHAR(40) NULL,
    time_of_birth  TIME NULL,
    place_of_birth VARCHAR(120) NULL,
    UNIQUE KEY uq_horoscope_user (user_id),
    CONSTRAINT fk_horoscope_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Profile lifestyle (location coordinates)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS profile_lifestyle (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id            BIGINT UNSIGNED NOT NULL,
    latitude           DECIMAL(10,7) NULL,
    longitude          DECIMAL(10,7) NULL,
    willing_to_relocate TINYINT(1) NOT NULL DEFAULT 0,
    residency_status   VARCHAR(40) NULL,
    UNIQUE KEY uq_lifestyle_user (user_id),
    CONSTRAINT fk_lifestyle_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Profile partner preferences
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS profile_preferences (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id            BIGINT UNSIGNED NOT NULL,
    min_age            TINYINT UNSIGNED NULL,
    max_age            TINYINT UNSIGNED NULL,
    min_height_cm      SMALLINT UNSIGNED NULL,
    max_height_cm      SMALLINT UNSIGNED NULL,
    pref_religion      JSON NULL,
    pref_caste         JSON NULL,
    pref_education     JSON NULL,
    pref_location      JSON NULL,
    pref_marital_status JSON NULL,
    pref_mother_tongue JSON NULL,
    pref_occupation    JSON NULL,
    pref_income_min    VARCHAR(30) NULL,
    pref_diet          JSON NULL,
    UNIQUE KEY uq_preferences_user (user_id),
    CONSTRAINT fk_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Profile views (who viewed me)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS profile_views (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    viewer_id  BIGINT UNSIGNED NOT NULL,
    profile_id BIGINT UNSIGNED NOT NULL,
    viewed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_views_profile (profile_id, viewed_at),
    KEY idx_views_viewer (viewer_id, viewed_at),
    CONSTRAINT fk_views_viewer  FOREIGN KEY (viewer_id)  REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_views_profile FOREIGN KEY (profile_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Saved searches
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS profile_searches (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id      BIGINT UNSIGNED NOT NULL,
    name         VARCHAR(120) NOT NULL,
    filters_json JSON NOT NULL,
    alert_enabled TINYINT(1) NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_searches_user (user_id),
    CONSTRAINT fk_searches_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Profile blocks
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS profile_blocks (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    blocker_id BIGINT UNSIGNED NOT NULL,
    blocked_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_blocks (blocker_id, blocked_id),
    CONSTRAINT fk_blocks_blocker FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_blocks_blocked FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Profile reports (abuse)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS profile_reports (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    reporter_id BIGINT UNSIGNED NOT NULL,
    profile_id  BIGINT UNSIGNED NOT NULL,
    reason      VARCHAR(255) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_reports_reporter (reporter_id),
    KEY idx_reports_profile (profile_id),
    CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reports_profile  FOREIGN KEY (profile_id)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Profile hobbies (M2M tags)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS profile_hobbies (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    profile_id BIGINT UNSIGNED NOT NULL,
    hobby      VARCHAR(80) NOT NULL,
    KEY idx_hobbies_profile (profile_id),
    CONSTRAINT fk_hobbies_profile FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Profile verifications (ID / edu / phone)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS profile_verifications (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id      BIGINT UNSIGNED NOT NULL,
    type         ENUM('id','education','phone','address') NOT NULL,
    status       ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    document_url VARCHAR(255) NULL,
    verified_at  DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_verifications_user (user_id),
    CONSTRAINT fk_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Match notifications (realtime events queue)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS match_notifications (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL,
    type       VARCHAR(40) NOT NULL,
    payload    JSON NULL,
    read_at    DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_user (user_id, read_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Match scores (cached compatibility)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS match_scores (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    viewer_id   BIGINT UNSIGNED NOT NULL,
    target_id   BIGINT UNSIGNED NOT NULL,
    score       TINYINT UNSIGNED NOT NULL,
    computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_scores (viewer_id, target_id),
    KEY idx_scores_viewer (viewer_id, score DESC),
    CONSTRAINT fk_scores_viewer FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_scores_target FOREIGN KEY (target_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Privacy settings
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS privacy_settings (
    user_id            BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    profile_visibility TINYINT(1) NOT NULL DEFAULT 1,
    show_phone         TINYINT(1) NOT NULL DEFAULT 0,
    show_email         TINYINT(1) NOT NULL DEFAULT 0,
    show_photos        TINYINT(1) NOT NULL DEFAULT 1,
    show_online_status TINYINT(1) NOT NULL DEFAULT 1,
    receive_interests  TINYINT(1) NOT NULL DEFAULT 1,
    privacy_preset     ENUM('public','members','private') NOT NULL DEFAULT 'members',
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_privacy_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Activity log
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL,
    action     VARCHAR(60) NOT NULL,
    details    JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_activity_user (user_id, created_at),
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PERFORMANCE INDEXES
-- ============================================================================

CREATE INDEX idx_profiles_gender ON profiles(gender, marital_status, country, state, city);
CREATE INDEX idx_profiles_dob ON profiles(date_of_birth);
CREATE INDEX idx_profiles_income ON profiles(annual_income);

-- ============================================================================
-- SEED DATA
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Membership plans
-- ---------------------------------------------------------------------------
INSERT INTO membership_plans (code, name, duration_days, price_cents, features) VALUES
    ('FREE',    'Free',     36500, 0,    'Browse profiles, send limited interests'),
    ('SILVER',  'Silver',    180,  299900, 'View contact details, send unlimited messages'),
    ('GOLD',    'Gold',      365,  799900, 'Silver + priority placement, profile highlight'),
    ('PLATINUM','Platinum',  365, 1499900, 'Gold + dedicated relationship manager')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---------------------------------------------------------------------------
-- Demo user (password: "secret123" — bcrypt)
-- ---------------------------------------------------------------------------
INSERT INTO users (email, password_hash, is_active, is_verified) VALUES
    ('demo@matrimony.local', '$2y$10$4k3jqg3yZ2g7XmO9J6QjDeY7p4p3t4e5r6y7u8i9o0p1a2s3d4f5g', 1, 1)
ON DUPLICATE KEY UPDATE email = VALUES(email);

INSERT INTO profiles (user_id, first_name, last_name, gender, date_of_birth, marital_status,
                      religion, caste, mother_tongue, height_cm, education, occupation,
                      city, state, country, about_me)
SELECT id, 'Demo', 'User', 'female', '1995-06-15', 'never_married',
       'Hindu', 'Brahmin', 'Hindi', 165, 'B.Tech', 'Software Engineer',
       'Bengaluru', 'Karnataka', 'India',
       'Demo profile for local development.'
FROM users WHERE email = 'demo@matrimony.local'
ON DUPLICATE KEY UPDATE first_name = VALUES(first_name);

-- ============================================================================
-- END OF SCHEMA
-- ============================================================================
