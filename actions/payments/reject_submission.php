<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_BURSAR]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid request.');
    redirect('bursar/payments/submissions.php');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$note = trim($_POST['review_note'] ?? '');

if (!$id) {
    set_flash('error', 'Invalid submission ID.');
    redirect('bursar/payments/submissions.php');
}

if ($note === '') {
    set_flash('error', 'A rejection reason is required.');
    redirect("bursar/payments/review.php?id=$id");
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();
    
    // Lock submission
    $stmt = $pdo->prepare("SELECT * FROM payment_submissions WHERE id = :id FOR UPDATE");
    $stmt->execute(['id' => $id]);
    $sub = $stmt->fetch();
    
    if (!$sub) {
        throw new Exception("Submission not found.");
    }
    
    if ($sub['status'] !== 'PENDING') {
        throw new Exception("This submission has already been reviewed.");
    }
    
    // Update submission
    $updateStmt = $pdo->prepare("
        UPDATE payment_submissions 
        SET status = 'REJECTED', 
            reviewed_by = ?, 
            reviewed_at = NOW(),
            review_note = ?
        WHERE id = ?
    ");
    $updateStmt->execute([
        current_user_id(),
        $note,
        $id
    ]);
    
    // Notify Student
    $sStmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
    $sStmt->execute([$sub['student_id']]);
    $studentUserId = $sStmt->fetchColumn();
    
    if ($studentUserId) {
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
        $notifStmt->execute([
            $studentUserId,
            "Payment Proof Rejected",
            "Your payment proof of NGN {$sub['amount']} was rejected. Reason: {$note}"
        ]);
    }
    
    // Audit Log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([
        current_user_id(),
        'REJECT_PAYMENT_SUBMISSION',
        'payment_submission',
        $id,
        "Rejected submission for NGN {$sub['amount']} with reason: {$note}"
    ]);
    
    $pdo->commit();
    set_flash('success', "Submission rejected successfully.");
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in reject_submission: " . $e->getMessage());
    set_flash('error', 'Unable to reject submission due to a database error.');
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
}

redirect('bursar/payments/submissions.php');
