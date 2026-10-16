-- ============================================================================
-- Migration 012: rate_limits table (replaces runtime CREATE TABLE)
-- ============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rate_limits (
    `key` VARCHAR(190) NOT NULL PRIMARY KEY,
    attempts INT NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
