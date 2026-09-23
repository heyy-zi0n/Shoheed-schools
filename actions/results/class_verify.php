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

if (!$classId || !$batchId) {
    set_flash('error', 'Invalid request parameters.');
    redirect("teacher/results/class_verification.php?class={$classId}");
}

try {
    $pdo = get_db_connection();
    
    // Verify assignment
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
    
    // Check if ALL subjects are SUBMITTED
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, ss.status
        FROM class_subjects cs
        JOIN subjects s ON cs.subject_id = s.id
        LEFT JOIN subject_submissions ss ON ss.subject_id = s.id AND ss.result_batch_id = :batch_id
        WHERE cs.class_id = :class_id
    ");
    $stmt->execute(['batch_id' => $batchId, 'class_id' => $classId]);
    $subjects = $stmt->fetchAll();
    
    $allSubmitted = true;
    foreach ($subjects as $sub) {
        if ($sub['status'] !== 'SUBMITTED') {
            $allSubmitted = false;
            break;
        }
    }
    
    if (!$allSubmitted || empty($subjects)) {
        throw new Exception("Cannot verify class. All expected subjects must be SUBMITTED.");
    }
    
    // Update batch
    $stmt = $pdo->prepare("UPDATE result_batches SET status = 'CLASS_TEACHER_VERIFIED', verified_by = ?, verified_at = NOW() WHERE id = ?");
    $stmt->execute([$staffId, $batchId]);
    
    // Log history
    $histStmt = $pdo->prepare("INSERT INTO result_workflow_history (result_batch_id, user_id, previous_status, new_status, comments) VALUES (?, ?, ?, ?, ?)");
    $histStmt->execute([$batchId, $userId, 'SUBMITTED', 'CLASS_TEACHER_VERIFIED', "Class teacher verified results."]);
    
    // Notify principals
    $prStmt = $pdo->prepare("SELECT u.id as user_id FROM staff s JOIN users u ON s.user_id = u.id JOIN roles r ON u.role_id = r.id WHERE r.name = ?");
    $prStmt->execute([ROLE_PRINCIPAL]);
    $principals = $prStmt->fetchAll();
    
    $className = $pdo->query("SELECT name FROM classes WHERE id = " . (int)$classId)->fetchColumn();
    
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    foreach ($principals as $pr) {
        $notifStmt->execute([
            $pr['user_id'],
            "Class Results Verified",
            "Results for {$className} have been verified by the Class Teacher and await your approval."
        ]);
    }
    
    $pdo->commit();
    set_flash('success', 'Class results successfully verified and sent to Principal.');
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in class_verify: " . $e->getMessage());
    set_flash('error', 'Unable to verify the class due to a database error.');
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
}

redirect("teacher/results/class_verification.php?class={$classId}");
