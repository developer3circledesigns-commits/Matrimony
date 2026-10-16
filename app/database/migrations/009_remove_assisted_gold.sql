-- Migration 009: Remove Assisted Gold plan (retired)
-- Deactivates the ASSISTED_GOLD poster plan on databases where 008 already
-- seeded it. Deactivation (not hard delete) preserves purchase/membership
-- history snapshots; PlanService only lists is_active = 1 plans, so new
-- purchases of Assisted Gold are blocked. Platinum moves to display_order 3.
-- Idempotent — safe to re-run.

UPDATE membership_plans SET is_active = 0 WHERE code = 'ASSISTED_GOLD';

DELETE FROM membership_plans WHERE code = 'ASSISTED_GOLD' AND id NOT IN (SELECT plan_id FROM memberships) AND id NOT IN (SELECT plan_id FROM purchases WHERE plan_id IS NOT NULL);

UPDATE membership_plans SET display_order = 3 WHERE code = 'PLATINUM';
