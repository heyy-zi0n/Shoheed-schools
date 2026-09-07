<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_CLASS_TEACHER]);

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
    $userId = current_user_id();
    $activeSession = $pdo->query("SELECT * FROM academic_sessions WHERE is_active = 1")->fetch();
    
    $stmt = $pdo->prepare("SELECT s.id FROM staff s JOIN class_teacher_assignments cta ON cta.staff_id = s.id WHERE s.user_id = :uid AND cta.class_id = :cid AND cta.academic_session_id = :sess");
    $stmt->execute(['uid' => $userId, 'cid' => $classId, 'sess' => $activeSession['id']]);
    $staffId = $stmt->fetchColumn();
    
    if (!$staffId) {
        throw new Exception("You are not authorized to verify this class.");
    }
    
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
    $prStmt = $pdo->prepare("SELECT u.id as user_id FROM staff s JOIN users u ON s.user_id = u.id WHERE u.role_id = ?");
    $prStmt->execute([ROLE_PRINCIPAL]);
    $principals = $prStmt->fetchAll();
    
    $className = $pdo->query("SELECT name FROM classes WHERE id = " . (int)$classId)->fetchColumn();
    
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    foreach ($principals as $pr) {
        $notifStmt->execute([
            $pr['user_id'],
            "Class Results Verified",
            "Results for {$className} have been verified by the Class Teacher and await your approval.",
            'CLASS_VERIFIED'
        ]);
    }
    
    $pdo->commit();
    set_flash('success', 'Class results successfully verified and sent to Principal.');
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
}

redirect("teacher/results/class_verification.php?class={$classId}");
