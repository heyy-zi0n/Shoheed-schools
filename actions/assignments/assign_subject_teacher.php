<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_role([ROLE_PRINCIPAL]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('principal/assignments/subjects.php');
}

$classId = filter_var($_POST['class_id'] ?? null, FILTER_VALIDATE_INT);
$subjectId = filter_var($_POST['subject_id'] ?? null, FILTER_VALIDATE_INT);
$staffId = filter_var($_POST['staff_id'] ?? null, FILTER_VALIDATE_INT);

$activeSession = get_active_academic_session();
if (!$activeSession || !$classId || !$subjectId || !$staffId) {
    set_flash('error', 'Invalid parameters or no active session.');
    redirect("principal/assignments/subjects.php?class={$classId}");
}

$pdo = get_db_connection();

try {
    $pdo->beginTransaction();

    // Pre-save validation: Check if ANY staff is assigned to this class-subject-session combination
    $checkStmt = $pdo->prepare("
        SELECT sta.id, sta.staff_id, s.first_name, s.last_name 
        FROM subject_teacher_assignments sta 
        JOIN staff s ON s.id = sta.staff_id 
        WHERE sta.class_id = ? AND sta.subject_id = ? AND sta.academic_session_id = ?
    ");
    $checkStmt->execute([$classId, $subjectId, $activeSession['id']]);
    $existingAssignment = $checkStmt->fetch();
    
    if ($existingAssignment) {
        $teacherName = "{$existingAssignment['first_name']} {$existingAssignment['last_name']}";
        
        $subInfoStmt = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
        $subInfoStmt->execute([$subjectId]);
        $subName = $subInfoStmt->fetchColumn() ?: 'this subject';
        
        $clsInfoStmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
        $clsInfoStmt->execute([$classId]);
        $clsName = $clsInfoStmt->fetchColumn() ?: 'this class';

        throw new Exception("{$subName} for {$clsName} already has {$teacherName} assigned as its Subject Teacher for the {$activeSession['name']} academic session.");
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO subject_teacher_assignments (class_id, subject_id, staff_id, academic_session_id) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$classId, $subjectId, $staffId, $activeSession['id']]);
    } catch (PDOException $pdoEx) {
        if ($pdoEx->getCode() == 23000) {
            throw new Exception("This subject assignment already exists or violates a unique constraint.");
        }
        throw $pdoEx;
    }
    
    $actionDesc = "Assigned staff ID {$staffId} to subject ID {$subjectId} for class ID {$classId}";

    // Audit Log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([
        current_user_id(),
        'SUBJECT_TEACHER_ASSIGNED',
        'subject',
        $subjectId,
        $actionDesc
    ]);

    $pdo->commit();
    set_flash('success', 'Subject teacher assignment updated.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
}

redirect("principal/assignments/subjects.php?class={$classId}");
