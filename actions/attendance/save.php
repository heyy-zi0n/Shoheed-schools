<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('teacher/dashboard.php');
}

$classId = filter_var($_POST['class_id'] ?? null, FILTER_VALIDATE_INT);
$attendanceDate = trim((string)($_POST['attendance_date'] ?? ''));
$parsedDate = $attendanceDate !== '' ? DateTime::createFromFormat('Y-m-d', $attendanceDate) : false;
$attendance = $_POST['attendance'] ?? []; // array of student_id => status

if (!$classId || !$parsedDate || $parsedDate->format('Y-m-d') !== $attendanceDate) {
    set_flash('error', 'Invalid class or attendance date format (expected YYYY-MM-DD).');
    redirect('teacher/dashboard.php');
}

try {
    $pdo = get_db_connection();
    
    // Verify assignment (security check)
    $userId = current_user_id();
    $staffId = current_staff_id();
    if (!$staffId) {
        throw new Exception("Access denied. You do not have a staff profile.");
    }
    
    $activeSession = get_active_academic_session();
    $activeTerm = $activeSession ? get_active_term($activeSession['id']) : null;
    
    if (!$activeSession || !$activeTerm) {
        throw new Exception("No active academic session or term found.");
    }
    
    if (!staff_can_access_class_teacher_function($staffId, $classId, $activeSession['id'])) {
        throw new Exception("You are not authorized to record attendance for this class.");
    }
    
    $enrolledStmt = $pdo->prepare("SELECT student_id FROM student_enrollments WHERE class_id = ? AND academic_session_id = ? AND status = 'ACTIVE'");
    $enrolledStmt->execute([$classId, $activeSession['id']]);
    $enrolledMap = array_flip($enrolledStmt->fetchAll(PDO::FETCH_COLUMN));

    $pdo->beginTransaction();
    
    // Using INSERT ON DUPLICATE KEY UPDATE to prevent duplicate rows for the same student/date/session
    $stmt = $pdo->prepare("
        INSERT INTO attendance (student_id, class_id, academic_session_id, term_id, attendance_date, status, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE term_id = VALUES(term_id), class_id = VALUES(class_id), status = VALUES(status), recorded_by = VALUES(recorded_by)
    ");
    
    $validStatuses = ['PRESENT', 'ABSENT', 'LATE', 'EXCUSED'];
    
    foreach ($attendance as $studentId => $status) {
        $studentIdInt = filter_var($studentId, FILTER_VALIDATE_INT);
        if (!$studentIdInt || !isset($enrolledMap[$studentIdInt])) {
            continue;
        }
        $statusUpper = strtoupper(trim((string)$status));
        if (in_array($statusUpper, $validStatuses, true)) {
            $stmt->execute([
                $studentIdInt,
                $classId,
                $activeSession['id'],
                $activeTerm['id'],
                $attendanceDate,
                $statusUpper,
                $userId
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
