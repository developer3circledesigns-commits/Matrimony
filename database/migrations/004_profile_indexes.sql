-- ============================================================================
-- Migration 004: Additional indexes for profile search performance
-- ============================================================================
-- Run: mysql -u root matrimony < database/migrations/004_profile_indexes.sql
-- ============================================================================

-- Composite index for religion/caste/tongue filtering (common search pattern)
CREATE INDEX idx_profiles_religion_caste_tongue ON profiles(religion, caste, mother_tongue);

-- Composite index for location-based search
CREATE INDEX idx_profiles_city_state ON profiles(city, state);

-- Index for education/occupation filtering
CREATE INDEX idx_profiles_education_occupation ON profiles(education, occupation);

-- Index for marital_status + gender combo searches
CREATE INDEX idx_profiles_marital_gender ON profiles(marital_status, gender);

-- Index for work_location filtering
CREATE INDEX idx_profiles_work_location ON profiles(work_location);

-- Composite index for created_by + created_at (audit queries)
CREATE INDEX idx_profiles_created ON profiles(created_by, created_at);

-- Index for photos status (admin moderation queries)
CREATE INDEX idx_photos_status ON profile_photos(status, created_at);

-- Composite index for matches (mutual match lookups)
CREATE INDEX idx_matches_status_created ON matches(status, created_at);

-- Index for activity_log action type queries
CREATE INDEX idx_activity_action ON activity_log(action, created_at);

-- Index for message read status queries
CREATE INDEX idx_messages_read ON messages(receiver_id, read_at, created_at);
