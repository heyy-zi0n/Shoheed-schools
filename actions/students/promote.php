<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('admin/students/promotion.php');
}

$sourceSessionId = filter_var($_POST['source_session_id'] ?? null, FILTER_VALIDATE_INT);
$sourceClassId = filter_var($_POST['source_class_id'] ?? null, FILTER_VALIDATE_INT);
$targetSessionId = filter_var($_POST['target_session_id'] ?? null, FILTER_VALIDATE_INT);
$targetClassId = filter_var($_POST['target_class_id'] ?? null, FILTER_VALIDATE_INT);
$markGraduated = !empty($_POST['mark_graduated']);
$actions = $_POST['action'] ?? []; // enrollment_id => action

if (!$sourceSessionId || !$sourceClassId || !$targetSessionId) {
    set_flash('error', 'Missing required session or class context.');
    redirect('admin/students/promotion.php');
}

if (!$markGraduated && !$targetClassId) {
    set_flash('error', 'Missing target class for promotion.');
    redirect('admin/students/promotion.php');
}

if (empty($actions) || !is_array($actions)) {
    set_flash('error', 'No actions selected.');
    redirect("admin/students/promotion.php?source_session_id={$sourceSessionId}&source_class_id={$sourceClassId}&target_session_id={$targetSessionId}");
}

try {
    $pdo = get_db_connection();
    
    // 3. Verify target academic session
    $tsStmt = $pdo->prepare("SELECT id FROM academic_sessions WHERE id = ?");
    $tsStmt->execute([$targetSessionId]);
    if (!$tsStmt->fetch()) {
        throw new Exception("Target academic session does not exist.");
    }
    
    // 4. Verify target class
    if (!$markGraduated) {
        $tcStmt = $pdo->prepare("SELECT id FROM classes WHERE id = ?");
        $tcStmt->execute([$targetClassId]);
        if (!$tcStmt->fetch()) {
            throw new Exception("Target class does not exist.");
        }
    }

    $pdo->beginTransaction();

    $processed = 0;
    $skipped = 0;
    
    $checkSourceStmt = $pdo->prepare("SELECT * FROM student_enrollments WHERE id = ? FOR UPDATE");
    $checkTargetStmt = $pdo->prepare("SELECT id FROM student_enrollments WHERE student_id = ? AND academic_session_id = ?");
    $updateSourceStmt = $pdo->prepare("UPDATE student_enrollments SET status = ? WHERE id = ?");
    $insertTargetStmt = $pdo->prepare("INSERT INTO student_enrollments (student_id, academic_session_id, class_id, status) VALUES (?, ?, ?, 'ACTIVE')");
    $updateStudentStatusStmt = $pdo->prepare("UPDATE students SET status = 'GRADUATED' WHERE id = ?");

    foreach ($actions as $enrollmentId => $action) {
        if ($action === 'skip') {
            $skipped++;
            continue;
        }

        // 1. Verify source enrollment exists and belongs to correct context
        $checkSourceStmt->execute([$enrollmentId]);
        $enrollment = $checkSourceStmt->fetch();
        
        if (!$enrollment) {
            throw new Exception("Enrollment ID {$enrollmentId} not found.");
        }
        
        if ($enrollment['academic_session_id'] != $sourceSessionId || $enrollment['class_id'] != $sourceClassId) {
            throw new Exception("Enrollment {$enrollmentId} context mismatch.");
        }
        
        if ($enrollment['status'] !== 'ACTIVE') {
            // Already processed
            $skipped++;
            continue;
        }

        $studentId = $enrollment['student_id'];
        
        // Determine what is happening
        $isPromoting = in_array($action, ['promote', 'override_promote'], true);
        $isRepeating = in_array($action, ['repeat', 'override_repeat'], true);
        
        if (!$isPromoting && !$isRepeating) {
            continue; // Safety fallback
        }

        // 5. Ensure target enrollment does not already exist (only if not graduating)
        if (!$markGraduated || ($markGraduated && $isRepeating)) {
            $checkTargetStmt->execute([$studentId, $targetSessionId]);
            if ($checkTargetStmt->fetch()) {
                throw new Exception("Student {$studentId} already has an enrollment in the target session.");
            }
        }

        // 7. Mark source enrollment appropriately
        $newSourceStatus = $isPromoting ? 'PROMOTED' : 'REPEATED';
        $updateSourceStmt->execute([$newSourceStatus, $enrollmentId]);
        
        // 8. Insert new target enrollment (or mark as graduated)
        if ($markGraduated && $isPromoting) {
            // Graduate the student
            $updateStudentStatusStmt->execute([$studentId]);
        } else {
            // New enrollment
            $nextClassId = $isPromoting ? $targetClassId : $sourceClassId; // If repeating, stay in same class
            $insertTargetStmt->execute([$studentId, $targetSessionId, $nextClassId]);
        }
        
        $processed++;
    }

    // 9. Write audit entry
    if ($processed > 0) {
        $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, 'BATCH_PROMOTION', 'session', ?, ?)")
            ->execute([current_user_id(), $sourceSessionId, "Processed {$processed} students from class {$sourceClassId} to session {$targetSessionId}."]);
    }

    // 10. Commit
    $pdo->commit();
    
    set_flash('success', "Batch promotion logic executed successfully. Processed: {$processed}, Skipped: {$skipped}.");
    redirect("admin/students/promotion.php?source_session_id={$sourceSessionId}&source_class_id={$sourceClassId}&target_session_id={$targetSessionId}");

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
    redirect("admin/students/promotion.php?source_session_id={$sourceSessionId}&source_class_id={$sourceClassId}&target_session_id={$targetSessionId}");
}
