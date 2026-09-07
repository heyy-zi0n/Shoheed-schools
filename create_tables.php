<?php
require_once __DIR__ . '/config/database.php';
try {
    $config = require __DIR__ . '/config/database.php';
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // 1. Attendance table with uniqueness
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        class_id INT NOT NULL,
        academic_session_id INT NOT NULL,
        term_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        status ENUM('PRESENT','ABSENT','LATE','EXCUSED') NOT NULL DEFAULT 'PRESENT',
        recorded_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_date_session (student_id, attendance_date, academic_session_id),
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id),
        FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id),
        FOREIGN KEY (term_id) REFERENCES terms(id),
        FOREIGN KEY (recorded_by) REFERENCES staff(id) ON DELETE SET NULL
    ) ENGINE=InnoDB;
    ");

    // 2. Announcements
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        target_audience ENUM('ALL','STAFF','STUDENTS','PARENTS','PUBLIC') NOT NULL DEFAULT 'ALL',
        is_published TINYINT(1) NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB;
    ");

    // 3. Notifications (already created but ensure it exists)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(50) NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;
    ");

    // 4. Receipts table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_id INT NOT NULL UNIQUE,
        receipt_number VARCHAR(50) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;
    ");

    // 5. result_workflow_history — ensure exists
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS result_workflow_history (
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
    ");

    // 6. Add verified_by / verified_at to result_batches if not present
    try {
        $pdo->exec("ALTER TABLE result_batches ADD COLUMN verified_by INT NULL AFTER status");
        $pdo->exec("ALTER TABLE result_batches ADD COLUMN verified_at DATETIME NULL AFTER verified_by");
    } catch (PDOException $e) { /* columns already exist */ }

    // 7. class_teacher_assignments
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS class_teacher_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        staff_id INT NOT NULL,
        class_id INT NOT NULL,
        academic_session_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_class_teacher_session (class_id, academic_session_id),
        FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;
    ");

    // 8. class_subjects
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS class_subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT NOT NULL,
        subject_id INT NOT NULL,
        UNIQUE KEY uq_class_subject (class_id, subject_id),
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;
    ");

    // 9. Ensure invoice_items exists
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS invoice_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_invoice_id INT NOT NULL,
        description VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        FOREIGN KEY (student_invoice_id) REFERENCES student_invoices(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;
    ");

    // 10. Add REVISION_REQUIRED to result_batches status enum if not present
    try {
        $pdo->exec("ALTER TABLE result_batches MODIFY COLUMN status ENUM('DRAFT','SUBMITTED','REVISION_REQUIRED','CLASS_TEACHER_VERIFIED','PRINCIPAL_APPROVED','PUBLISHED') NOT NULL DEFAULT 'DRAFT'");
    } catch (PDOException $e) { /* already done */ }

    // 11. Seed class_subjects — all subjects into all classes if empty
    $classSubjectCount = $pdo->query("SELECT COUNT(*) FROM class_subjects")->fetchColumn();
    if ($classSubjectCount == 0) {
        $classes = $pdo->query("SELECT id FROM classes")->fetchAll(PDO::FETCH_COLUMN);
        $subjects = $pdo->query("SELECT id FROM subjects")->fetchAll(PDO::FETCH_COLUMN);
        $csStmt = $pdo->prepare("INSERT IGNORE INTO class_subjects (class_id, subject_id) VALUES (?, ?)");
        foreach ($classes as $cid) {
            foreach ($subjects as $sid) {
                $csStmt->execute([$cid, $sid]);
            }
        }
        echo "Seeded class_subjects. ";
    }

    echo "All schema updates applied successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
