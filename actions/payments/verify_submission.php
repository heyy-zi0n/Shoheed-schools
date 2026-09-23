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
if (!$id) {
    set_flash('error', 'Invalid submission ID.');
    redirect('bursar/payments/submissions.php');
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
    
    if ($sub['status'] !== 'PENDING' || $sub['verified_payment_id'] !== null) {
        throw new Exception("This submission has already been reviewed.");
    }
    
    // Use canonical finance service to record the verified payment
    // It will automatically handle the invoice locking, balance validation, payments insert, receipt generation, invoice status update, audit logging, and student notification.
    $paymentResult = record_official_payment(
        $pdo,
        (int)$sub['invoice_id'],
        (float)$sub['amount'],
        'Bank Transfer', // Enforced method for submissions
        $sub['transfer_reference'],
        $sub['payment_date'],
        current_user_id()
    );
    
    // Update submission
    $updateStmt = $pdo->prepare("
        UPDATE payment_submissions 
        SET status = 'VERIFIED', 
            reviewed_by = ?, 
            reviewed_at = NOW(), 
            verified_payment_id = ? 
        WHERE id = ?
    ");
    $updateStmt->execute([
        current_user_id(),
        $paymentResult['payment_id'],
        $id
    ]);
    
    // Record audit for the verification explicitly
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([
        current_user_id(),
        'VERIFY_PAYMENT_SUBMISSION',
        'payment_submission',
        $id,
        "Verified submission for NGN {$sub['amount']}. Created payment {$paymentResult['payment_id']} and receipt {$paymentResult['receipt_number']}."
    ]);
    
    $pdo->commit();
    set_flash('success', "Payment verified and recorded successfully. Receipt generated: {$paymentResult['receipt_number']}");
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in verify_submission: " . $e->getMessage());
    set_flash('error', 'Unable to verify payment due to a database error.');
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
}

redirect('bursar/payments/submissions.php');
