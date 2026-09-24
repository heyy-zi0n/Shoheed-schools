-- Migration 011: Academic Terms Constraints
-- Add unique constraint to terms table to prevent duplicate terms within the same session

ALTER TABLE terms
    ADD UNIQUE KEY uq_session_term_name (academic_session_id, name);
