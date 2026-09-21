-- Add teaching capabilities to staff table
ALTER TABLE staff 
ADD COLUMN can_teach_subjects TINYINT(1) NOT NULL DEFAULT 0,
ADD COLUMN can_be_class_teacher TINYINT(1) NOT NULL DEFAULT 0;

-- Migrate existing staff data
-- SUBJECT_TEACHER and CLASS_TEACHER
UPDATE staff s
JOIN users u ON s.user_id = u.id
JOIN roles r ON u.role_id = r.id
SET s.can_teach_subjects = 1, s.can_be_class_teacher = 1
WHERE r.name IN ('SUBJECT_TEACHER', 'CLASS_TEACHER');

-- PRINCIPAL
UPDATE staff s
JOIN users u ON s.user_id = u.id
JOIN roles r ON u.role_id = r.id
SET s.can_teach_subjects = 1, s.can_be_class_teacher = 1
WHERE r.name = 'PRINCIPAL';

-- ADMIN is intentionally left untouched as 0 unless explicitly configured manually.
