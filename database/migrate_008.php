<?php
/**
 * Run migration 008: V1 Completion schema changes.
 * Usage: C:\xampp\php\php.exe database/migrate_008.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: This script may only be executed from the command line.');
}

require_once __DIR__ . '/../config/bootstrap.php';
$pdo = get_db_connection();

// 1. fee_structures.is_active
try {
    $pdo->exec("ALTER TABLE fee_structures ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER name");
    echo "Added is_active to fee_structures\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate column')) {
        echo "fee_structures.is_active already exists\n";
    } else throw $e;
}

// 2. student_invoices.fee_structure_id
try {
    $pdo->exec("ALTER TABLE student_invoices ADD COLUMN fee_structure_id INT NULL AFTER term_id");
    echo "Added fee_structure_id to student_invoices\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate column')) {
        echo "student_invoices.fee_structure_id already exists\n";
    } else throw $e;
}

// 3. FK for fee_structure_id
try {
    $pdo->exec("ALTER TABLE student_invoices ADD CONSTRAINT fk_inv_fee_structure FOREIGN KEY (fee_structure_id) REFERENCES fee_structures(id) ON DELETE SET NULL");
    echo "Added FK for fee_structure_id\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate key name') || str_contains($e->getMessage(), 'already exists')) {
        echo "FK fk_inv_fee_structure already exists\n";
    } else throw $e;
}

// 4. Drop old UNIQUE on student_invoices (student_id, academic_session_id, term_id)
try {
    $pdo->exec("ALTER TABLE student_invoices DROP INDEX student_id");
    echo "Dropped old student_invoices unique index\n";
} catch (PDOException $e) {
    echo "Old index not found or already dropped: " . $e->getMessage() . "\n";
}

// 5. Add new composite unique including fee_structure_id
try {
    $pdo->exec("ALTER TABLE student_invoices ADD UNIQUE KEY uq_invoice_student_structure (student_id, academic_session_id, term_id, fee_structure_id)");
    echo "Added new invoice unique constraint\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate key name')) {
        echo "New unique constraint already exists\n";
    } else throw $e;
}

// 6. subject_submissions
$pdo->exec("CREATE TABLE IF NOT EXISTS subject_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    result_batch_id INT NOT NULL,
    subject_id INT NOT NULL,
    status ENUM('DRAFT','SUBMITTED','REVISION_REQUIRED') DEFAULT 'DRAFT',
    comments TEXT NULL,
    UNIQUE KEY uq_batch_subject (result_batch_id, subject_id),
    FOREIGN KEY (result_batch_id) REFERENCES result_batches(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB");
echo "subject_submissions table ready\n";

echo "\nMigration 008 complete.\n";
