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

    // Check if the assignment actually exists
    $checkStmt = $pdo->prepare("
        SELECT id FROM subject_teacher_assignments 
        WHERE staff_id = ? AND class_id = ? AND subject_id = ? AND academic_session_id = ?
    ");
    $checkStmt->execute([$staffId, $classId, $subjectId, $activeSession['id']]);
    $assignment = $checkStmt->fetch();
    
    if (!$assignment) {
        throw new Exception("This subject assignment does not exist or has already been removed.");
    }

    // Check if academic history exists (e.g. submitted scores or result batches)
    // For subject teachers, we check if they have entered any scores for this subject in the current session.
    $historyCheck = $pdo->prepare("
        SELECT 1 FROM scores s 
        JOIN result_batches rb ON s.result_batch_id = rb.id 
        WHERE s.subject_id = ? AND s.entered_by = ? AND rb.class_id = ? AND rb.academic_session_id = ?
        LIMIT 1
    ");
    $historyCheck->execute([$subjectId, $staffId, $classId, $activeSession['id']]);
    
    if ($historyCheck->fetch()) {
        $subInfoStmt = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
        $subInfoStmt->execute([$subjectId]);
        $subName = $subInfoStmt->fetchColumn() ?: 'this subject';
        
        $clsInfoStmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
        $clsInfoStmt->execute([$classId]);
        $clsName = $clsInfoStmt->fetchColumn() ?: 'this class';

        throw new Exception("This teacher has already recorded results for {$subName} in {$clsName} for the {$activeSession['name']} academic session. The assignment cannot be removed until the existing academic records are resolved.");
    }

    // Remove assignment
    $stmt = $pdo->prepare("
        DELETE FROM subject_teacher_assignments 
        WHERE staff_id = ? AND class_id = ? AND subject_id = ? AND academic_session_id = ?
    ");
    $stmt->execute([$staffId, $classId, $subjectId, $activeSession['id']]);

    $actionDesc = "Removed staff ID {$staffId} from subject ID {$subjectId} for class ID {$classId}";

    // Audit Log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([
        current_user_id(),
        'SUBJECT_TEACHER_UNASSIGNED',
        'subject',
        $subjectId,
        $actionDesc
    ]);

    $pdo->commit();
    set_flash('success', 'Subject teacher unassigned successfully.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
}

redirect("principal/assignments/subjects.php?class={$classId}");
