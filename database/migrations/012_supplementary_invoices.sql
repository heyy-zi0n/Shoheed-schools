-- Migration 012: Supplementary Invoices and Fee Item Traceability
-- Implements support for generating secondary invoices when new items are added to a Fee Structure.

-- 1. Drop existing unique constraints that prevent multiple invoices per term/structure
ALTER TABLE student_invoices DROP INDEX `student_id`;
ALTER TABLE student_invoices DROP INDEX `uq_invoice_student_structure`;

-- Add back necessary non-unique index for student lookups
CREATE INDEX `idx_student_id` ON student_invoices(`student_id`);

-- 2. Add invoice_type to differentiate standard and supplementary invoices
ALTER TABLE student_invoices
ADD COLUMN invoice_type ENUM('STANDARD', 'SUPPLEMENTARY') DEFAULT 'STANDARD' AFTER status;

-- 3. Add source_fee_item_id to invoice_items to track which fee item generated the line item
ALTER TABLE invoice_items
ADD COLUMN source_fee_item_id INT NULL AFTER student_invoice_id,
ADD CONSTRAINT fk_invoice_items_source FOREIGN KEY (source_fee_item_id) REFERENCES fee_items(id) ON DELETE SET NULL;

-- 4. Safe Backfill: Link historical invoice items to their source fee items
-- We do this using a JOIN against the fee_structures linked to the invoice, matching by name and amount.
UPDATE invoice_items ii
JOIN student_invoices si ON ii.student_invoice_id = si.id
JOIN fee_items fi ON si.fee_structure_id = fi.fee_structure_id
    AND ii.name = fi.name
    AND ii.amount = fi.amount
SET ii.source_fee_item_id = fi.id
WHERE ii.source_fee_item_id IS NULL AND si.fee_structure_id IS NOT NULL;
