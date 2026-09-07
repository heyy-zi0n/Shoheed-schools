<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_CLASS_TEACHER]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('teacher/dashboard.php');
}

$classId = filter_var($_POST['class_id'] ?? null, FILTER_VALIDATE_INT);
$attendanceDate = filter_var($_POST['attendance_date'] ?? null, FILTER_SANITIZE_STRING);
$attendance = $_POST['attendance'] ?? []; // array of student_id => status

if (!$classId || !$attendanceDate) {
    set_flash('error', 'Invalid request parameters.');
    redirect('teacher/dashboard.php');
}

try {
    $pdo = get_db_connection();
    
    // Verify assignment (security check)
    $userId = current_user_id();
    $activeSession = $pdo->query("SELECT * FROM academic_sessions WHERE is_active = 1")->fetch();
    $activeTerm = $pdo->query("SELECT * FROM terms WHERE is_active = 1")->fetch();
    
    if (!$activeSession || !$activeTerm) {
        throw new Exception("No active academic session or term found.");
    }
    
    $stmt = $pdo->prepare("SELECT s.id FROM staff s JOIN class_teacher_assignments cta ON cta.staff_id = s.id WHERE s.user_id = :uid AND cta.class_id = :cid AND cta.academic_session_id = :sess");
    $stmt->execute(['uid' => $userId, 'cid' => $classId, 'sess' => $activeSession['id']]);
    $staffId = $stmt->fetchColumn();
    
    if (!$staffId) {
        throw new Exception("You are not authorized to record attendance for this class.");
    }
    
    $pdo->beginTransaction();
    
    // Using INSERT ON DUPLICATE KEY UPDATE to prevent duplicate rows for the same student/date/session
    $stmt = $pdo->prepare("
        INSERT INTO attendance (student_id, class_id, academic_session_id, term_id, attendance_date, status, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), recorded_by = VALUES(recorded_by)
    ");
    
    $validStatuses = ['PRESENT', 'ABSENT', 'LATE', 'EXCUSED'];
    
    foreach ($attendance as $studentId => $status) {
        if (in_array($status, $validStatuses)) {
            $stmt->execute([
                $studentId,
                $classId,
                $activeSession['id'],
                $activeTerm['id'],
                $attendanceDate,
                $status,
                $staffId
            ]);
        }
    }
    
    // Audit Log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([$userId, 'UPDATE_ATTENDANCE', 'class', $classId, "Recorded attendance for class {$classId} on {$attendanceDate}"]);

    $pdo->commit();
    set_flash('success', "Attendance saved successfully for {$attendanceDate}.");
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
}

redirect("teacher/attendance/index.php?class={$classId}&date={$attendanceDate}");
