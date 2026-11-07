<?php

/**
 * core/finance.php
 * Canonical finance logic for the application.
 */

/**
 * Generates a deterministic, collision-free receipt number from an auto-increment payment ID.
 */
function format_receipt_number(int $paymentId): string {
    return 'REC-' . date('ym') . '-' . str_pad((string)$paymentId, 5, '0', STR_PAD_LEFT);
}

/**
 * Recalculates and updates a student invoice's status ('PENDING', 'PARTIAL', 'PAID')
 * based on verified payments vs. total_amount.
 */
function recalculate_invoice_status(PDO $pdo, int $invoiceId): string {
    $invStmt = $pdo->prepare("SELECT total_amount, status FROM student_invoices WHERE id = ?");
    $invStmt->execute([$invoiceId]);
    $inv = $invStmt->fetch();
    if (!$inv) {
        throw new Exception("Invoice not found.");
    }

    $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_invoice_id = ?");
    $paidStmt->execute([$invoiceId]);
    $paid = (float)$paidStmt->fetchColumn();
    $total = (float)$inv['total_amount'];

    if ($paid <= 0) {
        $newStatus = 'PENDING';
    } elseif ($paid < $total) {
        $newStatus = 'PARTIAL';
    } else {
        $newStatus = 'PAID';
    }

    if ($inv['status'] !== $newStatus) {
        $updStmt = $pdo->prepare("UPDATE student_invoices SET status = ? WHERE id = ?");
        $updStmt->execute([$newStatus, $invoiceId]);
    }

    return $newStatus;
}

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
    $receiptNo = format_receipt_number((int)$paymentId);
    $stmt = $pdo->prepare("INSERT INTO receipts (payment_id, receipt_number) VALUES (?, ?)");
    $stmt->execute([$paymentId, $receiptNo]);
    
    // 5. Update Invoice Status
    recalculate_invoice_status($pdo, $invoiceId);
    
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
