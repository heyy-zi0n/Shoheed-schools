<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('teacher/results/index.php');
}

$classId = filter_var($_POST['class_id'] ?? null, FILTER_VALIDATE_INT);
$subjectId = filter_var($_POST['subject_id'] ?? null, FILTER_VALIDATE_INT);
$actionType = $_POST['action_type'] ?? 'save_draft';
$scores = $_POST['scores'] ?? [];

if (!$classId || !$subjectId) {
    set_flash('error', 'Invalid request parameters.');
    redirect('teacher/results/index.php');
}

try {
    $pdo = get_db_connection();
    
    // Get active session and term
    $activeSession = get_active_academic_session();
    $activeTerm = $pdo->query("SELECT * FROM terms WHERE is_active = 1")->fetch();
    
    if (!$activeSession || !$activeTerm) {
        throw new Exception("No active academic session or term found.");
    }
    
    // Verify assignment (security check)
    $staffId = current_staff_id();
    if (!$staffId) {
        throw new Exception("Access denied. You do not have a staff profile.");
    }
    
    if (!staff_can_access_subject_assignment($staffId, $classId, $subjectId, $activeSession['id'])) {
        throw new Exception("You are not authorized to enter scores for this subject and class.");
    }
    
    $pdo->beginTransaction();
    
    // 1. Get or Create Batch (Class Level)
    $stmt = $pdo->prepare("SELECT * FROM result_batches WHERE class_id = :cid AND academic_session_id = :sid AND term_id = :tid FOR UPDATE");
    $stmt->execute([
        'cid' => $classId,
        'sid' => $activeSession['id'],
        'tid' => $activeTerm['id']
    ]);
    $batch = $stmt->fetch();
    
    if ($batch) {
        $batchId = $batch['id'];
        $batchStatus = $batch['status'];
        
        // If the CLASS batch is already verified/approved, block all edits
        if (in_array($batchStatus, ['CLASS_TEACHER_VERIFIED', 'PRINCIPAL_APPROVED', 'PUBLISHED'])) {
            throw new Exception("The result batch for this class is already verified and locked.");
        }
    } else {
        // Create batch
        $stmt = $pdo->prepare("INSERT INTO result_batches (class_id, academic_session_id, term_id, status) VALUES (?, ?, ?, 'DRAFT')");
        $stmt->execute([$classId, $activeSession['id'], $activeTerm['id']]);
        $batchId = $pdo->lastInsertId();
    }
    
    // 2. Get or Create Subject Submission
    $stmt = $pdo->prepare("SELECT * FROM subject_submissions WHERE result_batch_id = ? AND subject_id = ? FOR UPDATE");
    $stmt->execute([$batchId, $subjectId]);
    $subSub = $stmt->fetch();
    
    $targetSubjectStatus = ($actionType === 'submit') ? 'SUBMITTED' : 'DRAFT';
    
    if ($subSub) {
        if ($subSub['status'] === 'SUBMITTED') {
            throw new Exception("These scores have already been submitted and are locked from editing.");
        }
        $stmt = $pdo->prepare("UPDATE subject_submissions SET status = ?, comments = NULL WHERE id = ?");
        $stmt->execute([$targetSubjectStatus, $subSub['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO subject_submissions (result_batch_id, subject_id, status) VALUES (?, ?, ?)");
        $stmt->execute([$batchId, $subjectId, $targetSubjectStatus]);
    }
    
    // 3. Save Scores
    $delStmt = $pdo->prepare("DELETE FROM scores WHERE result_batch_id = ? AND subject_id = ?");
    $delStmt->execute([$batchId, $subjectId]);
    
    $insStmt = $pdo->prepare("INSERT INTO scores (result_batch_id, student_id, subject_id, ca_score, exam_score, total_score, entered_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($scores as $studentId => $data) {
        $ca = isset($data['ca']) && $data['ca'] !== '' ? min(40, max(0, (float)$data['ca'])) : null;
        $ex = isset($data['exam']) && $data['exam'] !== '' ? min(60, max(0, (float)$data['exam'])) : null;
        
        if ($ca !== null || $ex !== null) {
            $total = ($ca ?? 0) + ($ex ?? 0);
            $insStmt->execute([$batchId, $studentId, $subjectId, $ca, $ex, $total, $staffId]);
        }
    }
    
    // 4. Notifications
    if ($targetSubjectStatus === 'SUBMITTED') {
        // Find Class Teacher
        $ctStmt = $pdo->prepare("
            SELECT u.id as user_id
            FROM class_teacher_assignments cta
            JOIN staff s ON cta.staff_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE cta.class_id = ? AND cta.academic_session_id = ?
        ");
        $ctStmt->execute([$classId, $activeSession['id']]);
        $classTeachers = $ctStmt->fetchAll();
        
        $className = $pdo->query("SELECT name FROM classes WHERE id = " . (int)$classId)->fetchColumn();
        $subjectName = $pdo->query("SELECT name FROM subjects WHERE id = " . (int)$subjectId)->fetchColumn();
        
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        foreach ($classTeachers as $ct) {
            $notifStmt->execute([
                $ct['user_id'],
                "Scores Submitted",
                "Scores for {$subjectName} in {$className} have been submitted for verification.",
                'RESULT_SUBMITTED'
            ]);
        }
    }
    
    // 5. Audit log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([
        $userId,
        $targetSubjectStatus === 'SUBMITTED' ? 'SUBMIT_SCORES' : 'SAVE_SCORES_DRAFT',
        'result_batch',
        $batchId,
        "Subject teacher saved scores for subject {$subjectId} in class {$classId}."
    ]);

    $pdo->commit();
    set_flash('success', $actionType === 'submit' ? 'Scores submitted successfully.' : 'Scores saved as draft.');
    
    redirect("teacher/results/enter.php?class={$classId}&subject={$subjectId}");
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
    redirect("teacher/results/enter.php?class={$classId}&subject={$subjectId}");
}
