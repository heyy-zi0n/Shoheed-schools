-- Public Staff Directory Migration

-- 1. Make user_id nullable to support directory-only staff
ALTER TABLE staff MODIFY user_id INT NULL;

-- 2. Add Public Directory Fields
ALTER TABLE staff 
    ADD COLUMN staff_category ENUM('TEACHING', 'NON_TEACHING') NULL DEFAULT NULL AFTER last_name,
    ADD COLUMN public_designation VARCHAR(150) NULL AFTER staff_category,
    ADD COLUMN short_bio TEXT NULL AFTER public_designation,
    ADD COLUMN qualification VARCHAR(255) NULL AFTER short_bio,
    ADD COLUMN public_email VARCHAR(150) NULL AFTER qualification,
    ADD COLUMN public_phone VARCHAR(50) NULL AFTER public_email,
    ADD COLUMN show_in_directory TINYINT(1) NOT NULL DEFAULT 0 AFTER public_phone,
    ADD COLUMN display_order INT NOT NULL DEFAULT 100 AFTER show_in_directory,
    ADD COLUMN status ENUM('ACTIVE', 'INACTIVE') DEFAULT 'ACTIVE' AFTER can_be_class_teacher;

-- 3. Backfill known staff categories based on existing portal roles
-- Teachers and Principal are TEACHING
UPDATE staff s
JOIN users u ON s.user_id = u.id
JOIN roles r ON u.role_id = r.id
SET s.staff_category = 'TEACHING', 
    s.public_designation = CASE 
        WHEN r.name = 'PRINCIPAL' THEN 'Principal'
        WHEN r.name = 'CLASS_TEACHER' THEN 'Class Teacher'
        WHEN r.name = 'SUBJECT_TEACHER' THEN 'Subject Teacher'
        ELSE NULL
    END
WHERE r.name IN ('PRINCIPAL', 'CLASS_TEACHER', 'SUBJECT_TEACHER');

-- Bursar is NON_TEACHING
UPDATE staff s
JOIN users u ON s.user_id = u.id
JOIN roles r ON u.role_id = r.id
SET s.staff_category = 'NON_TEACHING',
    s.public_designation = 'Bursar'
WHERE r.name = 'BURSAR';

-- Admin is NON_TEACHING by default but kept explicitly as NON_TEACHING (or could leave NULL, but Administrator is usually Non-Teaching unless configured)
UPDATE staff s
JOIN users u ON s.user_id = u.id
JOIN roles r ON u.role_id = r.id
SET s.staff_category = 'NON_TEACHING',
    s.public_designation = 'Administrator'
WHERE r.name = 'ADMIN';

-- Note: show_in_directory is 0 by default. Existing records are intentionally kept hidden 
-- until Admin explicitly reviews and enables them for public display to prevent accidental leakage.
