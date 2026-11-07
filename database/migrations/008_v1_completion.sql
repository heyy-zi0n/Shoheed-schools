-- Migration 008: V1 Completion
-- Adds is_active to fee_structures, fee_structure_id to student_invoices,
-- and a unique constraint for duplicate invoice prevention.

-- 1. Add is_active to fee_structures (not nullable so future structures can be toggled)
ALTER TABLE fee_structures
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER name;

-- 2. Add fee_structure_id FK to student_invoices (nullable to preserve existing rows)
ALTER TABLE student_invoices
    ADD COLUMN fee_structure_id INT NULL AFTER term_id,
    ADD CONSTRAINT fk_inv_fee_structure FOREIGN KEY (fee_structure_id) REFERENCES fee_structures(id) ON DELETE SET NULL;

-- 3. Tighten invoice uniqueness to also include fee_structure_id so multiple structures
--    per same session/term/class can each have their own invoice if needed, but a student
--    cannot have the same invoice generated twice from the same fee structure.
-- Note: existing UNIQUE(student_id, academic_session_id, term_id) is too broad - drop first.
ALTER TABLE student_invoices
    DROP INDEX student_id;

ALTER TABLE student_invoices
    ADD UNIQUE KEY uq_invoice_student_structure (student_id, academic_session_id, term_id, fee_structure_id);

-- 4. Add subject_submissions table if not already present (idempotent)
CREATE TABLE IF NOT EXISTS subject_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    result_batch_id INT NOT NULL,
    subject_id INT NOT NULL,
    status ENUM('DRAFT','SUBMITTED','REVISION_REQUIRED') DEFAULT 'DRAFT',
    comments TEXT NULL,
    UNIQUE KEY uq_batch_subject (result_batch_id, subject_id),
    FOREIGN KEY (result_batch_id) REFERENCES result_batches(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;
