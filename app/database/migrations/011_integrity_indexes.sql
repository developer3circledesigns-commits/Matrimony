-- ============================================================================
-- Migration 011: integrity + lookup indexes
-- Idempotent (the runner tolerates "Duplicate key"/"already exists").
-- ============================================================================
SET NAMES utf8mb4;

-- A purchase may back at most one membership (nullable: many NULLs allowed).
ALTER TABLE memberships ADD UNIQUE KEY uq_memberships_purchase (purchase_id);

-- Fast phone lookup (sign-in / dedupe checks).
CREATE INDEX idx_users_phone_norm ON users (phone_normalized);

-- Admin moderation queue filtering.
CREATE INDEX idx_profiles_moderation ON profiles (moderation_status);
