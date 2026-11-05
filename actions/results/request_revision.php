<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('teacher/results/class_verification.php');
}

$classId = filter_var($_POST['class_id'] ?? null, FILTER_VALIDATE_INT);
$batchId = filter_var($_POST['batch_id'] ?? null, FILTER_VALIDATE_INT);
$subjectId = filter_var($_POST['subject_id'] ?? null, FILTER_VALIDATE_INT);
$comments = trim($_POST['comments'] ?? '');

if (!$classId || !$batchId || !$subjectId || empty($comments)) {
    set_flash('error', 'Invalid request parameters.');
    redirect("teacher/results/review_subject.php?class={$classId}&subject={$subjectId}");
}

try {
    $pdo = get_db_connection();
    
    // Verify assignment (security check)
    $staffId = current_staff_id();
    if (!$staffId) {
        throw new Exception("Access denied. You do not have a staff profile.");
    }
    
    $activeSession = get_active_academic_session();
    if (!$activeSession) {
        throw new Exception("No active session found.");
    }
    
    if (!staff_can_access_class_teacher_function($staffId, $classId, $activeSession['id'])) {
        throw new Exception("You are not authorized to verify this class.");
    }
    
    $userId = current_user_id(); // for audit logs
    
    $pdo->beginTransaction();
    
    // Update subject submission
    $stmt = $pdo->prepare("UPDATE subject_submissions SET status = 'REVISION_REQUIRED', comments = ? WHERE result_batch_id = ? AND subject_id = ? AND status = 'SUBMITTED'");
    $stmt->execute([$comments, $batchId, $subjectId]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception("Subject is not in a valid state for revision.");
    }
    
    // Also set batch status to REVISION_REQUIRED if not already
    $stmt = $pdo->prepare("UPDATE result_batches SET status = 'REVISION_REQUIRED' WHERE id = ?");
    $stmt->execute([$batchId]);
    
    // Notify subject teacher
    $stStmt = $pdo->prepare("
        SELECT u.id as user_id
        FROM subject_teacher_assignments sta
        JOIN staff s ON sta.staff_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE sta.class_id = ? AND sta.subject_id = ? AND sta.academic_session_id = ?
    ");
    $stStmt->execute([$classId, $subjectId, $activeSession['id']]);
    $subjectTeachers = $stStmt->fetchAll();
    
    $className = $pdo->query("SELECT name FROM classes WHERE id = " . (int)$classId)->fetchColumn();
    $subjectName = $pdo->query("SELECT name FROM subjects WHERE id = " . (int)$subjectId)->fetchColumn();
    
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    foreach ($subjectTeachers as $st) {
        $notifStmt->execute([
            $st['user_id'],
            "Revision Required",
            "The Class Teacher has requested a revision for {$subjectName} in {$className}. Reason: {$comments}"
        ]);
    }
    
    // Audit Log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([$userId, 'REQUEST_REVISION', 'result_batch', $batchId, "Class teacher requested revision for subject {$subjectId} in class {$classId}."]);

    $pdo->commit();
    set_flash('success', 'Revision requested successfully.');
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in request_revision: " . $e->getMessage());
    set_flash('error', 'Unable to request revision due to a database error.');
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
}

redirect("teacher/results/review_subject.php?class={$classId}&subject={$subjectId}");
