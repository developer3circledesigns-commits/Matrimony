-- ---------------------------------------------------------------------------
-- 013: Payment hardening — email outbox dedupe/links, webhook summaries,
--      dispute ledger, refund lifecycle, payment indexes.
-- Additive and idempotent. Run with app/database/migrate.php.
-- ---------------------------------------------------------------------------

SET time_zone = '+00:00';

-- Email outbox: link emails to a purchase/refund/dispute and dedupe retries.
ALTER TABLE email_outbox ADD COLUMN related_type VARCHAR(40) NULL;
ALTER TABLE email_outbox ADD COLUMN related_id BIGINT UNSIGNED NULL;
ALTER TABLE email_outbox ADD COLUMN dedupe_key VARCHAR(190) NULL;
ALTER TABLE email_outbox ADD COLUMN locked_at DATETIME NULL;
ALTER TABLE email_outbox ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Webhook storage: keep a minimal, non-PII summary instead of the full payload.
ALTER TABLE gateway_webhook_events ADD COLUMN payload_summary JSON NULL;
UPDATE gateway_webhook_events SET payload_redacted = NULL WHERE payload_redacted IS NOT NULL;

-- Refunds may originate from the gateway (webhook) with no admin requester.
ALTER TABLE refunds MODIFY COLUMN requested_by BIGINT UNSIGNED NULL;
ALTER TABLE refunds ADD COLUMN processed_at DATETIME NULL;
ALTER TABLE refunds ADD COLUMN gateway_status VARCHAR(40) NULL;

-- Dispute/chargeback ledger.
CREATE TABLE IF NOT EXISTS payment_disputes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  purchase_id BIGINT UNSIGNED NOT NULL,
  gateway_dispute_id VARCHAR(80) NOT NULL,
  gateway_payment_id VARCHAR(80) NULL,
  amount_paise INT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'INR',
  status VARCHAR(40) NOT NULL,
  reason_code VARCHAR(80) NULL,
  opened_at DATETIME NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dispute_gateway (gateway_dispute_id),
  KEY idx_dispute_purchase (purchase_id),
  CONSTRAINT fk_dispute_purchase FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes used by dashboards/reconciliation.
ALTER TABLE email_outbox ADD UNIQUE KEY uq_outbox_dedupe (dedupe_key);
ALTER TABLE email_outbox ADD KEY idx_outbox_related (related_type, related_id);
ALTER TABLE purchases ADD KEY idx_purchases_paid_at (paid_at);
ALTER TABLE purchases ADD KEY idx_purchases_created_at (created_at);
ALTER TABLE gateway_webhook_events ADD KEY idx_gw_event_type (event_type, received_at);
