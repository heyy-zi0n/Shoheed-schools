-- Migration 016: Audit Remediation (Indexes, Enum Normalization, and Schema Cleanup)

-- 1. Normalize attendance status ENUM to uppercase constants
ALTER TABLE attendance
    MODIFY COLUMN status ENUM('PRESENT', 'ABSENT', 'LATE', 'EXCUSED') NOT NULL;

UPDATE attendance SET status = UPPER(status);

-- 2. Add composite index for unread notification counts and listing in topbar/notifications page
ALTER TABLE notifications
    ADD INDEX idx_notifications_user_read_created (user_id, is_read, created_at);

-- 3. Add composite index for audit log action filtering and sorting
ALTER TABLE audit_logs
    ADD INDEX idx_audit_action_created (action, created_at);

-- 4. Allow 'PUBLIC' audience on announcements if needed while keeping existing values valid
ALTER TABLE announcements
    MODIFY COLUMN audience ENUM('ALL', 'STAFF', 'STUDENTS', 'PARENTS', 'PUBLIC') DEFAULT 'ALL';
