-- Migration 015: Report Card Terminal Duration & SDBA / Comments Evaluations
-- Adds terminal duration and attendance metadata to result_batches
-- and creates student_result_evaluations for per-student SDBA ratings (1-5),
-- attendance overrides, and Class Teacher / Principal comments.

ALTER TABLE result_batches
    ADD COLUMN term_begins DATE NULL AFTER term_id,
    ADD COLUMN term_ends DATE NULL AFTER term_begins,
    ADD COLUMN next_term_begins DATE NULL AFTER term_ends,
    ADD COLUMN duration_weeks INT UNSIGNED NULL DEFAULT 14 AFTER next_term_begins,
    ADD COLUMN times_school_opened INT UNSIGNED NULL AFTER duration_weeks;

CREATE TABLE IF NOT EXISTS student_result_evaluations (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
