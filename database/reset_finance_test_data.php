<?php
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

$appEnv = getenv('APP_ENV');
if ($appEnv !== 'development') {
    fwrite(STDERR, "Refusing to run: APP_ENV must be development.\n");
    exit(1);
}

if (!in_array('--confirm', $argv)) {
    echo "Usage: php reset_finance_test_data.php --confirm\n";
    echo "Warning: This will delete ALL transactional finance data (invoices, payments, receipts, submissions).\n";
    exit(1);
}

require_once __DIR__ . '/../config/bootstrap.php';
$pdo = get_db_connection();

function get_counts($pdo) {
    return [
        'student_invoices' => $pdo->query("SELECT COUNT(*) FROM student_invoices")->fetchColumn(),
        'invoice_items' => $pdo->query("SELECT COUNT(*) FROM invoice_items")->fetchColumn(),
        'payments' => $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn(),
        'payment_submissions' => $pdo->query("SELECT COUNT(*) FROM payment_submissions")->fetchColumn(),
        'receipts' => $pdo->query("SELECT COUNT(*) FROM receipts")->fetchColumn(),
        'fee_structures' => $pdo->query("SELECT COUNT(*) FROM fee_structures")->fetchColumn(),
        'fee_items' => $pdo->query("SELECT COUNT(*) FROM fee_items")->fetchColumn(),
        'school_bank_accounts' => $pdo->query("SELECT COUNT(*) FROM school_bank_accounts")->fetchColumn(),
        'students' => $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
    ];
}

$before = get_counts($pdo);

echo "--- BEFORE RESET COUNTS ---\n";
foreach ($before as $table => $count) {
    echo str_pad($table, 25) . ": $count\n";
}
echo "---------------------------\n";

try {
    $pdo->beginTransaction();

    // Explicit FK-safe deletion order
    $pdo->exec("DELETE FROM receipts");
    $pdo->exec("DELETE FROM payment_submissions");
    $pdo->exec("DELETE FROM payments");
    $pdo->exec("DELETE FROM invoice_items");
    $pdo->exec("DELETE FROM student_invoices");

    $pdo->commit();
    echo "Database transaction committed successfully.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    die("Database reset failed: " . $e->getMessage() . "\n");
}

// Clean storage
$deletedFiles = 0;
$proofDir = __DIR__ . '/../storage/payment_proofs/';
if (is_dir($proofDir)) {
    $files = scandir($proofDir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === '.gitkeep') continue;
        if (is_file($proofDir . $file)) {
            unlink($proofDir . $file);
            $deletedFiles++;
        }
    }
}
echo "Removed $deletedFiles payment proof files.\n";

$after = get_counts($pdo);
echo "\n--- AFTER RESET COUNTS ---\n";
foreach ($after as $table => $count) {
    echo str_pad($table, 25) . ": $count\n";
}
echo "--------------------------\n";

// Fetch current JSS1 Fee Structure
$stmt = $pdo->prepare("
    SELECT fs.id, a.name as session_name, t.name as term_name, c.name as class_name
    FROM fee_structures fs
    JOIN academic_sessions a ON fs.academic_session_id = a.id
    JOIN terms t ON fs.term_id = t.id
    JOIN classes c ON fs.class_id = c.id
    WHERE c.name LIKE '%JSS 1%' AND t.name LIKE '%First Term%'
    LIMIT 1
");
$stmt->execute();
$fs = $stmt->fetch(PDO::FETCH_ASSOC);

if ($fs) {
    echo "\n--- CURRENT JSS1 FEE STRUCTURE ---\n";
    echo "Session: " . $fs['session_name'] . "\n";
    echo "Term:    " . $fs['term_name'] . "\n";
    echo "Class:   " . $fs['class_name'] . "\n";

    $itemStmt = $pdo->prepare("SELECT name, amount FROM fee_items WHERE fee_structure_id = ? AND is_active = 1");
    $itemStmt->execute([$fs['id']]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    $total = 0;

    echo "Active Items:\n";
    foreach ($items as $item) {
        echo " - " . str_pad($item['name'], 20) . " ₦" . number_format($item['amount'], 2) . "\n";
        $total += $item['amount'];
    }
    echo str_pad("TOTAL", 23) . " ₦" . number_format($total, 2) . "\n";
}

echo "\nNotifications and Audit Logs were deliberately PRESERVED to avoid accidental destruction of non-finance history.\n";
