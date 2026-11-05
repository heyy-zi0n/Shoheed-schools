CREATE DATABASE IF NOT EXISTS shoheed_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shoheed_db;

-- 1. Roles
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 2. Users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('ACTIVE', 'INACTIVE') DEFAULT 'ACTIVE',
    last_login_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- 3. Staff
CREATE TABLE staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    staff_category ENUM('TEACHING', 'NON_TEACHING') NULL DEFAULT NULL,
    public_designation VARCHAR(150) NULL,
    short_bio TEXT NULL,
    qualification VARCHAR(255) NULL,
    public_email VARCHAR(150) NULL,
    public_phone VARCHAR(50) NULL,
    show_in_directory TINYINT(1) NOT NULL DEFAULT 0,
    display_order INT NOT NULL DEFAULT 100,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    profile_image VARCHAR(255) NULL,
    can_teach_subjects TINYINT(1) NOT NULL DEFAULT 0,
    can_be_class_teacher TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('ACTIVE', 'INACTIVE') DEFAULT 'ACTIVE',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Students
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL UNIQUE,
    admission_number VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    date_of_birth DATE NULL,
    admission_date DATE NULL,
    profile_image VARCHAR(255) NULL,
    status ENUM('ACTIVE', 'INACTIVE', 'GRADUATED', 'WITHDRAWN') DEFAULT 'ACTIVE',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5. Guardians
CREATE TABLE guardians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. Student Guardians Link
CREATE TABLE student_guardians (
    student_id INT NOT NULL,
    guardian_id INT NOT NULL,
    relationship VARCHAR(50) NULL,
    PRIMARY KEY (student_id, guardian_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (guardian_id) REFERENCES guardians(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. Academic Sessions & Terms
CREATE TABLE academic_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    is_active BOOLEAN DEFAULT FALSE,
    start_date DATE NULL,
    end_date DATE NULL
) ENGINE=InnoDB;

CREATE TABLE terms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    academic_session_id INT NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    start_date DATE NULL,
    end_date DATE NULL,
    UNIQUE KEY uq_session_term_name (academic_session_id, name),
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8. Classes
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE,
    level INT NOT NULL
) ENGINE=InnoDB;

-- 9. Subjects
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    code VARCHAR(20) NULL UNIQUE,
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB;

-- 10. Enrollments
CREATE TABLE student_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    academic_session_id INT NOT NULL,
    class_id INT NOT NULL,
    status ENUM('ACTIVE', 'PROMOTED', 'REPEATED', 'WITHDRAWN') DEFAULT 'ACTIVE',
    UNIQUE (student_id, academic_session_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id),
    FOREIGN KEY (class_id) REFERENCES classes(id)
) ENGINE=InnoDB;

-- 11. Teacher Assignments
CREATE TABLE class_teacher_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    class_id INT NOT NULL,
    academic_session_id INT NOT NULL,
    UNIQUE KEY uq_class_teacher_class_session (class_id, academic_session_id),
    UNIQUE KEY uq_class_teacher_staff_session (staff_id, academic_session_id),
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id)
) ENGINE=InnoDB;

CREATE TABLE subject_teacher_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    subject_id INT NOT NULL,
    class_id INT NOT NULL,
    academic_session_id INT NOT NULL,
    KEY (staff_id),
    UNIQUE KEY uq_subject_class_session (class_id, subject_id, academic_session_id),
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id)
) ENGINE=InnoDB;

CREATE TABLE class_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    UNIQUE (class_id, subject_id),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 12. Results
CREATE TABLE result_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    academic_session_id INT NOT NULL,
    term_id INT NOT NULL,
    term_begins DATE NULL,
    term_ends DATE NULL,
    next_term_begins DATE NULL,
    duration_weeks INT UNSIGNED NULL DEFAULT 14,
    times_school_opened INT UNSIGNED NULL,
    status ENUM('DRAFT', 'SUBMITTED', 'REVISION_REQUIRED', 'CLASS_TEACHER_VERIFIED', 'PRINCIPAL_APPROVED', 'PUBLISHED') DEFAULT 'DRAFT',
    verified_by INT NULL,
    verified_at DATETIME NULL,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    published_at DATETIME NULL,
    UNIQUE (class_id, academic_session_id, term_id),
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id),
    FOREIGN KEY (term_id) REFERENCES terms(id),
    FOREIGN KEY (verified_by) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE student_result_evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    result_batch_id INT NOT NULL,
    student_id INT NOT NULL,
    times_present INT UNSIGNED NULL,
    times_absent INT UNSIGNED NULL,
    obedience TINYINT UNSIGNED NULL,
    honesty TINYINT UNSIGNED NULL,
    self_control TINYINT UNSIGNED NULL,
    self_reliance TINYINT UNSIGNED NULL,
    use_of_initiative TINYINT UNSIGNED NULL,
    punctuality TINYINT UNSIGNED NULL,
    neatness TINYINT UNSIGNED NULL,
    perseverance TINYINT UNSIGNED NULL,
    attendance_rating TINYINT UNSIGNED NULL,
    attentiveness TINYINT UNSIGNED NULL,
    reading_writing TINYINT UNSIGNED NULL,
    verbal_comm TINYINT UNSIGNED NULL,
    sport_game TINYINT UNSIGNED NULL,
    inquisitiveness TINYINT UNSIGNED NULL,
    class_teacher_comment VARCHAR(500) NULL,
    principal_comment VARCHAR(500) NULL,
    updated_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_batch_student_eval (result_batch_id, student_id),
    FOREIGN KEY (result_batch_id) REFERENCES result_batches(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    result_batch_id INT NOT NULL,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    ca_score DECIMAL(5,2) NULL,
    exam_score DECIMAL(5,2) NULL,
    total_score DECIMAL(5,2) NULL,
    grade VARCHAR(5) NULL,
    remark VARCHAR(50) NULL,
    entered_by INT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE (result_batch_id, student_id, subject_id),
    FOREIGN KEY (result_batch_id) REFERENCES result_batches(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (entered_by) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE result_workflow_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    result_batch_id INT NOT NULL,
    user_id INT NULL,
    previous_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NOT NULL,
    comments TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (result_batch_id) REFERENCES result_batches(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 13. Attendance
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    academic_session_id INT NOT NULL,
    term_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present', 'Absent', 'Late', 'Excused') NOT NULL,
    recorded_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE (student_id, attendance_date, academic_session_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id),
    FOREIGN KEY (term_id) REFERENCES terms(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 14. Fees and Finance
CREATE TABLE fee_structures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academic_session_id INT NOT NULL,
    term_id INT NOT NULL,
    class_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    UNIQUE (academic_session_id, term_id, class_id),
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id),
    FOREIGN KEY (term_id) REFERENCES terms(id),
    FOREIGN KEY (class_id) REFERENCES classes(id)
) ENGINE=InnoDB;

CREATE TABLE fee_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fee_structure_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (fee_structure_id) REFERENCES fee_structures(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE student_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    academic_session_id INT NOT NULL,
    term_id INT NOT NULL,
    fee_structure_id INT DEFAULT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('PENDING', 'PARTIAL', 'PAID') DEFAULT 'PENDING',
    invoice_type ENUM('STANDARD', 'SUPPLEMENTARY') DEFAULT 'STANDARD',
    due_date DATE NULL,
    KEY idx_student_id (student_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id),
    FOREIGN KEY (term_id) REFERENCES terms(id),
    FOREIGN KEY (fee_structure_id) REFERENCES fee_structures(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_invoice_id INT NOT NULL,
    source_fee_item_id INT NULL,
    name VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (student_invoice_id) REFERENCES student_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (source_fee_item_id) REFERENCES fee_items(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_invoice_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_reference VARCHAR(100) NULL,
    payment_date DATE NOT NULL,
    recorded_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_invoice_id) REFERENCES student_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL UNIQUE,
    receipt_number VARCHAR(50) NOT NULL UNIQUE,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 15. Announcements & Notifications
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    author_id INT NULL,
    audience ENUM('ALL', 'STAFF', 'STUDENTS', 'PARENTS') DEFAULT 'ALL',
    is_public BOOLEAN DEFAULT FALSE,
    status ENUM('DRAFT', 'PUBLISHED') DEFAULT 'DRAFT',
    publish_date DATE NULL,
    expiry_date DATE NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 16. Audit Logs & Settings
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE `school_bank_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_name` varchar(100) NOT NULL,
  `account_name` varchar(150) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payment_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `sender_bank` varchar(100) NOT NULL,
  `transfer_reference` varchar(100) DEFAULT NULL,
  `proof_path` varchar(255) NOT NULL,
  `note` text,
  `status` enum('PENDING','VERIFIED','REJECTED') DEFAULT 'PENDING',
  `submitted_at` datetime DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `review_note` text,
  `verified_payment_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `reviewed_by` (`reviewed_by`),
  KEY `verified_payment_id` (`verified_payment_id`),
  CONSTRAINT `fk_pay_sub_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `fk_pay_sub_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `student_invoices` (`id`),
  CONSTRAINT `fk_pay_sub_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_sub_payment` FOREIGN KEY (`verified_payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE users
SET status = 'INACTIVE'
WHERE role_id = (SELECT id FROM roles WHERE name = 'ROLE_PARENT');
