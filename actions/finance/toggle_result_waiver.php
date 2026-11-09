<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN, ROLE_PRINCIPAL, ROLE_BURSAR]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

validate_csrf();

$studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
$sessionId = filter_input(INPUT_POST, 'academic_session_id', FILTER_VALIDATE_INT);
$termId = filter_input(INPUT_POST, 'term_id', FILTER_VALIDATE_INT);
$action = trim($_POST['action'] ?? '');
$reason = trim($_POST['reason'] ?? '');
$redirectUrl = trim($_POST['redirect_url'] ?? '');

if (!$studentId || !$sessionId || !$termId || !in_array($action, ['grant', 'revoke'], true)) {
    set_flash('error', 'Invalid clearance request parameters.');
    redirect($redirectUrl ?: 'bursar/invoices/index.php');
}

$pdo = get_db_connection();
$currentUserId = current_user_id();
$userRole = current_user_role();

// Verify student exists
$stStmt = $pdo->prepare("SELECT s.id, s.first_name, s.last_name, s.user_id FROM students s WHERE s.id = ?");
$stStmt->execute([$studentId]);
$student = $stStmt->fetch();
if (!$student) {
    set_flash('error', 'Student record not found.');
    redirect($redirectUrl ?: 'bursar/invoices/index.php');
}

// Verify session and term exist
$termStmt = $pdo->prepare("SELECT t.id, t.name as term_name, a.name as session_name FROM terms t JOIN academic_sessions a ON t.academic_session_id = a.id WHERE t.id = ? AND a.id = ?");
$termStmt->execute([$termId, $sessionId]);
$termMeta = $termStmt->fetch();
if (!$termMeta) {
    set_flash('error', 'Academic session or term not found.');
    redirect($redirectUrl ?: 'bursar/invoices/index.php');
}

try {
    $pdo->beginTransaction();

    if ($action === 'grant') {
        if ($reason === '') {
            $reason = 'Management clearance approved on parental plea.';
        }
        if (mb_strlen($reason) > 255) {
            $reason = mb_substr($reason, 0, 255);
        }

        $stmt = $pdo->prepare("
            INSERT INTO result_access_waivers (student_id, academic_session_id, term_id, granted_by, reason, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE 
                granted_by = VALUES(granted_by),
                reason = VALUES(reason),
                is_active = 1,
                updated_at = NOW()
        ");
        $stmt->execute([$studentId, $sessionId, $termId, $currentUserId, $reason]);

        // Audit log
        $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
        $auditStmt->execute([
            $currentUserId,
            'GRANT_RESULT_WAIVER',
            'student',
            $studentId,
            format_role_name($userRole) . " granted result clearance waiver for {$student['first_name']} {$student['last_name']} ({$termMeta['term_name']} {$termMeta['session_name']}). Reason: {$reason}"
        ]);

        // Notify student if user account exists
        if (!empty($student['user_id'])) {
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            $notifStmt->execute([
                $student['user_id'],
                'Result Clearance Approved',
                "Management has granted clearance for you to view your academic report for {$termMeta['term_name']} {$termMeta['session_name']}."
            ]);
        }

        $pdo->commit();
        set_flash('success', "Result access waiver granted successfully for {$student['first_name']} {$student['last_name']}.");
    } else {
        // Revoke
        $stmt = $pdo->prepare("
            UPDATE result_access_waivers 
            SET is_active = 0, updated_at = NOW() 
            WHERE student_id = ? AND academic_session_id = ? AND term_id = ?
        ");
        $stmt->execute([$studentId, $sessionId, $termId]);

        // Audit log
        $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
        $auditStmt->execute([
            $currentUserId,
            'REVOKE_RESULT_WAIVER',
            'student',
            $studentId,
            format_role_name($userRole) . " revoked result clearance waiver for {$student['first_name']} {$student['last_name']} ({$termMeta['term_name']} {$termMeta['session_name']})."
        ]);

        $pdo->commit();
        set_flash('success', "Result access waiver revoked for {$student['first_name']} {$student['last_name']}.");
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Result Waiver Error: " . $e->getMessage());
    set_flash('error', 'A database error occurred while updating the clearance waiver.');
}

redirect($redirectUrl ?: 'bursar/invoices/index.php');
