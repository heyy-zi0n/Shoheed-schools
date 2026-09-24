-- Migration 013: Fee Items Lifecycle
-- Adds support for archiving billed fee items instead of hard-deleting them.

ALTER TABLE fee_items
ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER amount;
