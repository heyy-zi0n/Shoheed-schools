<?php

/**
 * core/finance.php
 * Canonical finance logic for the application.
 */

/**
 * Records an official verified payment and generates a receipt.
 * 
 * Must be called within a database transaction context if caller is combining actions.
 * 
 * @param PDO $pdo
 * @param int $invoiceId The student_invoices.id
 * @param float $amount The payment amount to record
 * @param string $paymentMethod The payment method (e.g. 'Bank Transfer', 'Cash', 'POS')
 * @param string|null $paymentReference Optional payment reference
 * @param string $paymentDate The date of the payment (e.g. 'Y-m-d')
 * @param int $recordedBy The user ID of the staff member recording the payment
 * @return array Returns an array with ['payment_id' => int, 'receipt_number' => string] on success
 * @throws Exception If validation fails or amount exceeds outstanding balance
 */
function record_official_payment(PDO $pdo, int $invoiceId, float $amount, string $paymentMethod, ?string $paymentReference, string $paymentDate, int $recordedBy): array {
    // 1. Lock invoice (if not already locked by caller, this is a safe re-lock)
    $stmt = $pdo->prepare("SELECT * FROM student_invoices WHERE id = :id FOR UPDATE");
    $stmt->execute(['id' => $invoiceId]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        throw new Exception("Invoice not found.");
    }
    
    // 2. Verify amount doesn't exceed balance
    $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_invoice_id = :id");
    $paidStmt->execute(['id' => $invoiceId]);
    $currentPaid = (float)$paidStmt->fetchColumn();
    $total = (float)$invoice['total_amount'];
    
    $balance = $total - $currentPaid;
    
    if ($amount <= 0 || $amount > $balance) {
        throw new Exception("Invalid payment amount. Maximum allowed is " . number_format($balance, 2) . ".");
    }
    
    // 3. Insert Payment
    $stmt = $pdo->prepare("INSERT INTO payments (student_invoice_id, amount, payment_method, payment_reference, payment_date, recorded_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$invoiceId, $amount, $paymentMethod, $paymentReference !== '' ? $paymentReference : null, $paymentDate, $recordedBy]);
    $paymentId = $pdo->lastInsertId();
    
    // 4. Generate Receipt
    $receiptNo = 'REC-' . date('ym') . '-' . str_pad($paymentId, 5, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare("INSERT INTO receipts (payment_id, receipt_number) VALUES (?, ?)");
    $stmt->execute([$paymentId, $receiptNo]);
    
    // 5. Update Invoice Status
    $newPaid = $currentPaid + $amount;
    $newStatus = ($newPaid >= $total) ? 'PAID' : 'PARTIAL';
    
    if ($invoice['status'] !== $newStatus) {
        $stmt = $pdo->prepare("UPDATE student_invoices SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $invoiceId]);
    }
    
    // 6. Audit Log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([
        $recordedBy,
        'RECORD_PAYMENT',
        'payment',
        $paymentId,
        "Recorded payment of {$amount} for invoice {$invoiceId}. Receipt: {$receiptNo}"
    ]);
    
    // 7. Notify Student Only
    $stStmt = $pdo->prepare("
        SELECT u.id as student_user_id
        FROM student_invoices si
        JOIN students st ON si.student_id = st.id
        LEFT JOIN users u ON st.user_id = u.id
        WHERE si.id = ?
    ");
    $stStmt->execute([$invoiceId]);
    $audience = $stStmt->fetch();
    
    if ($audience && $audience['student_user_id']) {
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
        $notifStmt->execute([$audience['student_user_id'], "Payment Received", "A payment of ₦{$amount} was recorded. Receipt: {$receiptNo}"]);
    }
    
    return [
        'payment_id' => $paymentId,
        'receipt_number' => $receiptNo
    ];
}
