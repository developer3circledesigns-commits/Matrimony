-- ============================================================================
-- Migration 008: Secure admin, paid plans, Razorpay payments, memberships
-- Preserves existing data. Plain-MySQL syntax: safe to import via phpMyAdmin.
-- Preferred (idempotent, safe to re-run): php app/database/migrate.php
-- or the key-gated web endpoint /migrate?key=<CRON_KEY>.
-- ============================================================================
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------------
-- 1. Extend users: RBAC + account lifecycle + contact fields
-- ---------------------------------------------------------------------------
ALTER TABLE users
  ADD COLUMN role ENUM('user','moderator','admin','super_admin') NOT NULL DEFAULT 'user' AFTER password_hash,
  ADD COLUMN account_status ENUM('pending_verification','active','suspended','deactivated','deleted') NOT NULL DEFAULT 'active' AFTER role,
  ADD COLUMN email_normalized VARCHAR(190) NULL AFTER email,
  ADD COLUMN phone VARCHAR(20) NULL AFTER email_normalized,
  ADD COLUMN phone_normalized VARCHAR(20) NULL AFTER phone,
  ADD COLUMN email_verified_at DATETIME NULL AFTER phone_normalized,
  ADD COLUMN phone_verified_at DATETIME NULL AFTER email_verified_at,
  ADD COLUMN totp_secret VARCHAR(64) NULL AFTER phone_verified_at,
  ADD COLUMN must_reauth_at DATETIME NULL AFTER totp_secret;

-- Backfill normalized email
UPDATE users SET email_normalized = LOWER(TRIM(email)) WHERE email_normalized IS NULL;

-- Unique + lookup indexes (guarded via information_schema in migrate.php for older MySQL;
-- plain ADD UNIQUE fails if duplicates — migrate.php cleans first, so plain version here
-- is wrapped in idempotent runner. Keep plain DDL for fresh installs.)
-- NOTE: migrate.php executes these conditionally.

-- ---------------------------------------------------------------------------
-- 2. Extend membership_plans -> catalogue with paise, contacts, slug, order
-- ---------------------------------------------------------------------------
ALTER TABLE membership_plans
  ADD COLUMN slug VARCHAR(60) NULL AFTER code,
  ADD COLUMN description TEXT NULL AFTER name,
  ADD COLUMN price_paise INT UNSIGNED NULL AFTER price_cents,
  ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'INR' AFTER price_paise,
  ADD COLUMN contact_limit INT UNSIGNED NULL AFTER duration_days,
  ADD COLUMN is_unlimited TINYINT(1) NOT NULL DEFAULT 0 AFTER contact_limit,
  ADD COLUMN display_order INT NOT NULL DEFAULT 0 AFTER is_active,
  ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER display_order,
  ADD COLUMN updated_by BIGINT UNSIGNED NULL AFTER created_by,
  ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER updated_by,
  ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Backfill paise from legacy cents (legacy stored paise*100? treat legacy as paise if >100000 else*100 — safest: copy if null)
UPDATE membership_plans SET price_paise = price_cents WHERE price_paise IS NULL;
UPDATE membership_plans SET slug = LOWER(code) WHERE slug IS NULL;

-- ---------------------------------------------------------------------------
-- 3. Extend memberships: link to purchase, snapshots, full lifecycle
-- ---------------------------------------------------------------------------
ALTER TABLE memberships
  ADD COLUMN purchase_id BIGINT UNSIGNED NULL AFTER plan_id,
  ADD COLUMN plan_name_snapshot VARCHAR(80) NULL AFTER purchase_id,
  ADD COLUMN amount_paise INT UNSIGNED NULL AFTER plan_name_snapshot,
  ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'INR' AFTER amount_paise,
  ADD COLUMN duration_days_snapshot INT UNSIGNED NULL AFTER currency,
  ADD COLUMN contact_limit_snapshot INT UNSIGNED NULL AFTER duration_days_snapshot,
  ADD COLUMN is_unlimited_snapshot TINYINT(1) NOT NULL DEFAULT 0 AFTER contact_limit_snapshot,
  ADD COLUMN contacts_used INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_unlimited_snapshot,
  ADD COLUMN activated_by BIGINT UNSIGNED NULL AFTER contacts_used,
  ADD COLUMN activated_at DATETIME NULL AFTER activated_by,
  ADD COLUMN suspension_reason VARCHAR(255) NULL AFTER activated_at;

-- Widen legacy status enum to full lifecycle (MySQL: redefine column)
ALTER TABLE memberships MODIFY COLUMN status ENUM('pending','active','expired','suspended','revoked','replaced','cancelled') NOT NULL DEFAULT 'pending';

-- ---------------------------------------------------------------------------
-- 4. Purchases / payment orders (immutable snapshot per purchase)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS purchases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  public_reference VARCHAR(40) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  plan_id INT UNSIGNED NOT NULL,
  plan_name_snapshot VARCHAR(80) NOT NULL,
  amount_paise INT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'INR',
  duration_days_snapshot INT UNSIGNED NOT NULL,
  contact_limit_snapshot INT UNSIGNED NULL,
  is_unlimited_snapshot TINYINT(1) NOT NULL DEFAULT 0,
  gateway VARCHAR(20) NOT NULL DEFAULT 'razorpay',
  gateway_order_id VARCHAR(80) NULL,
  gateway_payment_id VARCHAR(80) NULL,
  payment_status ENUM('created','pending','paid_verified','failed','cancelled','refunded','disputed','needs_review') NOT NULL DEFAULT 'created',
  signature_verified_at DATETIME NULL,
  paid_at DATETIME NULL,
  failure_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_purchases_ref (public_reference),
  UNIQUE KEY uq_purchases_gw_order (gateway_order_id),
  UNIQUE KEY uq_purchases_gw_payment (gateway_payment_id),
  KEY idx_purchases_user (user_id, payment_status),
  KEY idx_purchases_status (payment_status),
  CONSTRAINT fk_purchases_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_purchases_plan FOREIGN KEY (plan_id) REFERENCES membership_plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 5. Gateway webhook events (idempotency via provider key)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS gateway_webhook_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  gateway VARCHAR(20) NOT NULL DEFAULT 'razorpay',
  provider_event_key VARCHAR(120) NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  purchase_id BIGINT UNSIGNED NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  processing_status ENUM('received','processed','duplicate','failed','ignored') NOT NULL DEFAULT 'received',
  error_message VARCHAR(255) NULL,
  payload_hash CHAR(64) NOT NULL,
  payload_redacted MEDIUMTEXT NULL,
  UNIQUE KEY uq_gw_event (gateway, provider_event_key),
  KEY idx_gw_purchase (purchase_id),
  CONSTRAINT fk_gw_purchase FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 6. Activation requests (payment verified -> admin activates)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activation_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  purchase_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('awaiting_admin','approved_activated','rejected','on_hold') NOT NULL DEFAULT 'awaiting_admin',
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  decision_reason VARCHAR(500) NULL,
  activated_membership_id BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_activation_purchase (purchase_id),
  KEY idx_activation_status (status),
  KEY idx_activation_user (user_id),
  CONSTRAINT fk_act_purchase FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  CONSTRAINT fk_act_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_act_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 7. Contact reveals ledger (unique viewer/target = one counted reveal)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_reveals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  viewer_user_id BIGINT UNSIGNED NOT NULL,
  target_user_id BIGINT UNSIGNED NOT NULL,
  membership_id BIGINT UNSIGNED NOT NULL,
  revealed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reveal_pair (viewer_user_id, target_user_id),
  KEY idx_reveal_membership (membership_id),
  KEY idx_reveal_viewer (viewer_user_id),
  CONSTRAINT fk_reveal_viewer FOREIGN KEY (viewer_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reveal_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reveal_membership FOREIGN KEY (membership_id) REFERENCES memberships(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 8. Notifications (in-panel)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(60) NOT NULL,
  title VARCHAR(160) NOT NULL,
  message TEXT NOT NULL,
  related_type VARCHAR(40) NULL,
  related_id BIGINT UNSIGNED NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notif_user (user_id, read_at, created_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 9. Audit logs (append-only; no UPDATE/DELETE grants for app user ideally)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id VARCHAR(80) NULL,
  meta_json JSON NULL,
  outcome VARCHAR(20) NOT NULL DEFAULT 'success',
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_actor (actor_user_id, created_at),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_action (action, created_at),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 10. Email outbox (commit state first, send async via cron)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_outbox (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  recipient_email VARCHAR(190) NOT NULL,
  recipient_user_id BIGINT UNSIGNED NULL,
  template_key VARCHAR(80) NOT NULL,
  subject VARCHAR(200) NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  body_text MEDIUMTEXT NULL,
  status ENUM('queued','sending','sent','failed') NOT NULL DEFAULT 'queued',
  attempts INT NOT NULL DEFAULT 0,
  next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  last_error VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_outbox_status (status, next_attempt_at),
  KEY idx_outbox_recipient (recipient_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 11. Refunds / payment adjustments (append-only)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS refunds (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  purchase_id BIGINT UNSIGNED NOT NULL,
  gateway_refund_id VARCHAR(80) NULL,
  amount_paise INT UNSIGNED NOT NULL,
  status ENUM('requested','processing','processed','failed','rejected') NOT NULL DEFAULT 'requested',
  reason VARCHAR(500) NOT NULL,
  requested_by BIGINT UNSIGNED NOT NULL,
  approved_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_refund_gw (gateway_refund_id),
  KEY idx_refund_purchase (purchase_id),
  CONSTRAINT fk_refund_purchase FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  CONSTRAINT fk_refund_requester FOREIGN KEY (requested_by) REFERENCES users(id),
  CONSTRAINT fk_refund_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 12. Password resets (hashed tokens, single-use, expiring)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reset_token (token_hash),
  KEY idx_reset_user (user_id),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 13. Profile moderation status + privacy hardening
-- ---------------------------------------------------------------------------
ALTER TABLE profiles
  ADD COLUMN moderation_status ENUM('pending','approved','rejected','hidden','flagged') NOT NULL DEFAULT 'approved' AFTER partner_prefs,
  ADD COLUMN moderation_reason VARCHAR(255) NULL AFTER moderation_status,
  ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0 AFTER moderation_reason;

ALTER TABLE profile_reports
  ADD COLUMN status ENUM('open','dismissed','warned','hidden','suspended','escalated') NOT NULL DEFAULT 'open' AFTER reason,
  ADD COLUMN reviewed_by BIGINT UNSIGNED NULL AFTER status,
  ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by,
  ADD COLUMN review_note VARCHAR(500) NULL AFTER reviewed_at;

ALTER TABLE privacy_settings
  ADD COLUMN allow_contact_reveal TINYINT(1) NOT NULL DEFAULT 1 AFTER receive_interests;

-- Prevent duplicate active interests (sender/recipient unique where active) — partial
-- uniqueness enforced in app code + this unique key for interested/mutual handled in service.

-- ---------------------------------------------------------------------------
-- 14. Seed the three poster plans (idempotent)
-- ---------------------------------------------------------------------------
INSERT INTO membership_plans (code, slug, name, description, duration_days, price_cents, price_paise, currency, contact_limit, is_unlimited, is_active, display_order, features) VALUES
  ('SILVER', 'silver', 'Silver Plan', '25 contacts · 45 days. Send interests while active. Protected contact reveals consume allowance (one per member).', 45, 100000, 100000, 'INR', 25, 0, 1, 1, 'Send interests, reveal up to 25 unique contacts'),
  ('GOLD', 'gold', 'Gold Plan', '45 contacts · 90 days. Send interests while active. Protected contact reveals consume allowance (one per member).', 90, 300000, 300000, 'INR', 45, 0, 1, 2, 'Send interests, reveal up to 45 unique contacts'),
  ('PLATINUM', 'platinum', 'Platinum Plan', 'Unlimited contacts · 1 year (365 days). All safety, privacy, block and rate-limit checks still apply.', 365, 1000000, 1000000, 'INR', NULL, 1, 1, 3, 'Send interests, unlimited contact reveals for 1 year')
ON DUPLICATE KEY UPDATE
  name = VALUES(name), description = VALUES(description), duration_days = VALUES(duration_days),
  price_cents = VALUES(price_cents), price_paise = VALUES(price_paise), currency = VALUES(currency),
  contact_limit = VALUES(contact_limit), is_unlimited = VALUES(is_unlimited),
  display_order = VALUES(display_order), features = VALUES(features);
