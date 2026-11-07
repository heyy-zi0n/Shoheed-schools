<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_PRINCIPAL]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('principal/results/index.php');
}

$batchId = filter_var($_POST['batch_id'] ?? null, FILTER_VALIDATE_INT);
$actionType = $_POST['action_type'] ?? '';

if (!$batchId || !in_array($actionType, ['approve', 'publish'])) {
    set_flash('error', 'Invalid request.');
    redirect('principal/results/index.php');
}

try {
    $pdo = get_db_connection();
    
    // Get staff id for this user
    $userId = current_user_id();
    $stmt = $pdo->prepare("SELECT id FROM staff WHERE user_id = :uid");
    $stmt->execute(['uid' => $userId]);
    $staffId = $stmt->fetchColumn();
    
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT * FROM result_batches WHERE id = :id FOR UPDATE");
    $stmt->execute(['id' => $batchId]);
    $batch = $stmt->fetch();
    
    if (!$batch) {
        throw new Exception("Batch not found.");
    }
    
    $currentStatus = $batch['status'];
    $newStatus = '';
    $dateField = '';
    
    if ($actionType === 'approve') {
        if ($currentStatus !== 'CLASS_TEACHER_VERIFIED') {
            throw new Exception("Cannot approve. Batch must be verified by the Class Teacher first.");
        }
        $newStatus = 'PRINCIPAL_APPROVED';
        $updateStmt = $pdo->prepare("UPDATE result_batches SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
        $updateStmt->execute([$newStatus, $staffId, $batchId]);
    } elseif ($actionType === 'publish') {
        if ($currentStatus !== 'PRINCIPAL_APPROVED') {
            throw new Exception("Batch must be approved before publishing.");
        }
        $newStatus = 'PUBLISHED';
        $updateStmt = $pdo->prepare("UPDATE result_batches SET status = ?, published_at = NOW() WHERE id = ?");
        $updateStmt->execute([$newStatus, $batchId]);
        
        // Notify Students and Parents
        $className = $pdo->query("SELECT name FROM classes WHERE id = " . (int)$batch['class_id'])->fetchColumn();
        $stStmt = $pdo->prepare("
            SELECT DISTINCT u.id as student_user_id, gu.id as guardian_user_id
            FROM scores s
            JOIN students st ON s.student_id = st.id
            LEFT JOIN users u ON st.user_id = u.id
            LEFT JOIN student_guardians sg ON st.id = sg.student_id
            LEFT JOIN guardians g ON sg.guardian_id = g.id
            LEFT JOIN users gu ON g.user_id = gu.id
            WHERE s.result_batch_id = ?
        ");
        $stStmt->execute([$batchId]);
        $audiences = $stStmt->fetchAll();
        
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
        
        $notifiedUsers = [];
        foreach ($audiences as $aud) {
            if ($aud['student_user_id'] && !in_array($aud['student_user_id'], $notifiedUsers)) {
                $notifStmt->execute([$aud['student_user_id'], "Results Published", "Your terminal academic report for {$className} is now available."]);
                $notifiedUsers[] = $aud['student_user_id'];
            }
            if ($aud['guardian_user_id'] && !in_array($aud['guardian_user_id'], $notifiedUsers)) {
                $notifStmt->execute([$aud['guardian_user_id'], "Results Published", "Terminal academic reports for {$className} have been published."]);
                $notifiedUsers[] = $aud['guardian_user_id'];
            }
        }
    }
    
    // Log history
    $histStmt = $pdo->prepare("INSERT INTO result_workflow_history (result_batch_id, user_id, previous_status, new_status, comments) VALUES (?, ?, ?, ?, ?)");
    $histStmt->execute([$batchId, $userId, $currentStatus, $newStatus, "Principal performed $actionType"]);

    // Log audit entry
    log_audit(
        $pdo,
        (int)$userId,
        $actionType === 'publish' ? 'PUBLISH_RESULTS' : 'APPROVE_RESULTS',
        'result_batch',
        (int)$batchId,
        "Principal performed {$actionType} on result batch #{$batchId} (class ID {$batch['class_id']})."
    );

    $pdo->commit();
    set_flash('success', "Batch successfully " . ($actionType === 'publish' ? 'published' : 'approved') . ".");
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in principal_approve: " . $e->getMessage());
    set_flash('error', 'Unable to process the request due to a database error.');
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
}

redirect('principal/results/index.php');
