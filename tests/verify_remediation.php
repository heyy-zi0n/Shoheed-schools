<?php
/**
 * tests/verify_remediation.php
 * CLI automated verification suite for Shoheed Private Schools Management System.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI execution only.');
}

require_once dirname(__DIR__) . '/config/bootstrap.php';

$passed = 0;
$failed = 0;

function assert_check(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "[PASS] {$name}\n";
    } else {
        $failed++;
        echo "[FAIL] {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

echo "=== Shoheed Schools Verification Suite ===\n\n";

$pdo = get_db_connection();

// 1. Database Schema & Constraints
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach (['users', 'roles', 'staff', 'students', 'guardians', 'student_guardians', 'student_invoices', 'invoice_items', 'payments', 'payment_submissions', 'receipts', 'result_batches', 'subject_submissions', 'scores', 'student_result_evaluations', 'attendance', 'announcements', 'notifications', 'audit_logs', 'login_attempts'] as $reqTable) {
    assert_check("Table exists: {$reqTable}", in_array($reqTable, $tables, true));
}

$attCols = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'status'")->fetch();
assert_check("attendance.status ENUM is uppercase", isset($attCols['Type']) && str_contains($attCols['Type'], "'PRESENT'"), $attCols['Type'] ?? '');

$annCols = $pdo->query("SHOW COLUMNS FROM announcements LIKE 'audience'")->fetch();
assert_check("announcements.audience supports PUBLIC", isset($annCols['Type']) && str_contains($annCols['Type'], "'PUBLIC'"), $annCols['Type'] ?? '');

$subCols = $pdo->query("SHOW COLUMNS FROM subject_submissions LIKE 'comments'")->fetch();
assert_check("subject_submissions.comments column exists", (bool)$subCols);

$notifIdx = $pdo->query("SHOW INDEX FROM notifications WHERE Key_name = 'idx_notifications_user_read_created'")->fetchAll();
assert_check("notifications composite index exists", count($notifIdx) >= 3);

$attUniq = $pdo->query("SHOW INDEX FROM attendance WHERE Non_unique = 0 AND Key_name != 'PRIMARY'")->fetchAll();
assert_check("attendance unique key exists", count($attUniq) >= 3);

// 2. Role Constants & Formatting (FUNC-002)
assert_check("ROLE_ADMIN constant equals 'ADMIN'", ROLE_ADMIN === 'ADMIN');
assert_check("format_role_name(ROLE_ADMIN) === 'Administrator'", format_role_name(ROLE_ADMIN) === 'Administrator');
assert_check("format_role_name(ROLE_CLASS_TEACHER) === 'Class Teacher'", format_role_name(ROLE_CLASS_TEACHER) === 'Class Teacher');
assert_check("get_dashboard_url_for_role(ROLE_BURSAR) resolves bursar/dashboard.php", str_ends_with(get_dashboard_url_for_role(ROLE_BURSAR), 'bursar/dashboard.php'));

// 3. Open Redirect Sanitization (SEC-004)
assert_check("Blocks external HTTPS URL", sanitize_internal_redirect_path('https://evil.example.com/steal', 'fallback.php') === 'fallback.php');
assert_check("Blocks protocol-relative URL", sanitize_internal_redirect_path('//evil.example.com/steal', 'fallback.php') === 'fallback.php');
assert_check("Blocks javascript: scheme", sanitize_internal_redirect_path('javascript:alert(1)', 'fallback.php') === 'fallback.php');
assert_check("Blocks CRLF header injection", sanitize_internal_redirect_path("teacher/dashboard.php\r\nSet-Cookie: a=b", 'fallback.php') === 'fallback.php');
assert_check("Allows valid relative path with query", sanitize_internal_redirect_path('teacher/results/class_verification.php?class=2', 'fallback.php') === 'teacher/results/class_verification.php?class=2');

// 4. Upload Filename Hardening (SEC-001)
$blockedDoubleExt = false;
try {
    assert_safe_original_filename('receipt.pdf.php');
} catch (Exception $e) {
    $blockedDoubleExt = true;
}
assert_check("Rejects double-extension .pdf.php filename", $blockedDoubleExt);

$blockedPhpExt = false;
try {
    assert_safe_original_filename('shell.phtml');
} catch (Exception $e) {
    $blockedPhpExt = true;
}
assert_check("Rejects executable .phtml filename", $blockedPhpExt);

$allowedCleanPdf = false;
try {
    assert_safe_original_filename('bank_transfer_receipt_2026.pdf');
    $allowedCleanPdf = true;
} catch (Exception $e) {
    $allowedCleanPdf = false;
}
assert_check("Accepts clean .pdf filename", $allowedCleanPdf);

// 5. Result Grading & Finance Helpers (FUNC-009, DB-003)
$gA = calculate_shoheed_grade(85.0);
$gC = calculate_shoheed_grade(55.0);
$gF = calculate_shoheed_grade(32.0);
assert_check("Grade 85 => A", ($gA['grade'] ?? '') === 'A');
assert_check("Grade 55 => C5", ($gC['grade'] ?? '') === 'C5');
assert_check("Grade 32 => F9", ($gF['grade'] ?? '') === 'F9');
assert_check("Receipt number format deterministic", preg_match('/^REC-\d{4}-00042$/', format_receipt_number(42)) === 1);

// 6. Student Directory Query & Pagination (PERF-003)
$dirRes = query_student_directory($pdo, '', null, '', 1, 10);
assert_check("query_student_directory() returns structured pagination array", isset($dirRes['students'], $dirRes['total'], $dirRes['pages'], $dirRes['page']));

// 7. Static Compiled Tailwind Asset Exists (PERF-001)
$twPath = dirname(__DIR__) . '/assets/css/tailwind.min.css';
assert_check("Compiled assets/css/tailwind.min.css exists and > 10 KB", is_file($twPath) && filesize($twPath) > 10240);

// 8. Exposed Root Scripts Removed (SEC-002)
foreach (['seed_test_accounts.php', 'describe_staff.php', 'show_tables.php', 'test_verify.php'] as $removedFile) {
    assert_check("Web-root script removed: {$removedFile}", !file_exists(dirname(__DIR__) . '/' . $removedFile));
}

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);
