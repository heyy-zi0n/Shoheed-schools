<?php
if (PHP_SAPI !== 'cli') {
    die("Test-data reset is CLI-only.\n");
}

require_once __DIR__ . '/../config/bootstrap.php';

if (getenv('APP_ENV') !== 'development') {
    die("ABORT: Reset is only allowed in the development environment.\n");
}

$isConfirm = (isset($argv[1]) && $argv[1] === '--confirm');

if (!$isConfirm) {
    echo "Shoheed Schools Clean Test Reset\n\n";
    echo "Will reset:\n";
    echo "- Results (scores, batches, submissions, history)\n";
    echo "- Attendance\n";
    echo "- Invoices (student_invoices, invoice_items)\n";
    echo "- Payments (payments, receipts, payment_submissions)\n";
    echo "- Notifications\n";
    echo "- Development audit logs\n\n";
    echo "Will preserve:\n";
    echo "- Users, Staff, Students, Guardians\n";
    echo "- Sessions, Terms, Classes, Subjects, Enrollments\n";
    echo "- Teaching assignments\n";
    echo "- Fee structures\n";
    echo "- School bank accounts\n\n";
    echo "Run again with --confirm to continue.\n";
    exit(0);
}

$pdo = get_db_connection();

// Tables to reset
$resetTables = [
    'receipts',
    'payment_submissions',
    'payments',
    'invoice_items',
    'student_invoices',
    'subject_submissions',
    'result_workflow_history',
    'scores',
    'result_batches',
    'attendance',
    'notifications',
    'audit_logs'
];

// Tables to preserve (for reporting)
$preserveTables = [
    'users',
    'staff',
    'students',
    'guardians',
    'student_guardians',
    'academic_sessions',
    'terms',
    'classes',
    'subjects',
    'student_enrollments',
    'class_teacher_assignments',
    'subject_teacher_assignments',
    'class_subjects',
    'fee_structures',
    'fee_items',
    'school_bank_accounts'
];

function getCounts($pdo, $tables) {
    $counts = [];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $counts[$table] = $stmt->fetchColumn();
    }
    return $counts;
}

$beforeReset = getCounts($pdo, $resetTables);
$beforePreserve = getCounts($pdo, $preserveTables);

echo "Shoheed Schools Clean Test Reset\n\n";
echo "Before Reset\n";
echo "---------------------------\n";
foreach ($beforeReset as $table => $count) {
    echo "$table: $count\n";
}
echo "\nPreserved Master Data (Before)\n";
echo "---------------------------\n";
foreach ($beforePreserve as $table => $count) {
    echo "$table: $count\n";
}
echo "\nResetting...\n";

// Gather file paths to delete before we remove the DB records
$stmt = $pdo->query("SELECT proof_path FROM payment_submissions WHERE proof_path IS NOT NULL");
$proofFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

try {
    $pdo->beginTransaction();

    // Perform deletions in specific child->parent order
    $pdo->exec("DELETE FROM receipts");
    $pdo->exec("DELETE FROM payment_submissions");
    $pdo->exec("DELETE FROM payments");
    $pdo->exec("DELETE FROM invoice_items");
    $pdo->exec("DELETE FROM student_invoices");
    
    $pdo->exec("DELETE FROM subject_submissions");
    $pdo->exec("DELETE FROM result_workflow_history");
    $pdo->exec("DELETE FROM scores");
    $pdo->exec("DELETE FROM result_batches");
    
    $pdo->exec("DELETE FROM attendance");
    
    $pdo->exec("DELETE FROM notifications");
    $pdo->exec("DELETE FROM audit_logs");

    $pdo->commit();
    echo "Database transaction committed successfully.\n\n";
    
    // Cleanup files
    $deletedFiles = 0;
    foreach ($proofFiles as $path) {
        $fullPath = __DIR__ . '/../' . ltrim($path, '/');
        // Validate path is inside storage/payment_proofs
        $realRoot = realpath(__DIR__ . '/../storage/payment_proofs');
        $realPath = realpath($fullPath);
        if ($realRoot && $realPath && strpos($realPath, $realRoot) === 0 && file_exists($realPath)) {
            unlink($realPath);
            $deletedFiles++;
        }
    }
    if ($deletedFiles > 0) {
        echo "Cleaned up $deletedFiles payment proof files.\n\n";
    }

} catch (Exception $e) {
    $pdo->rollBack();
    echo "ERROR: Reset failed. Transaction rolled back.\n";
    echo $e->getMessage() . "\n";
    exit(1);
}

$afterReset = getCounts($pdo, $resetTables);
$afterPreserve = getCounts($pdo, $preserveTables);

echo "After Reset\n";
echo "---------------------------\n";
foreach ($afterReset as $table => $count) {
    echo "$table: $count\n";
}

echo "\nPreserved Master Data (After)\n";
echo "---------------------------\n";
foreach ($afterPreserve as $table => $count) {
    echo "$table: $count\n";
}

echo "\nReset Complete.\n";
